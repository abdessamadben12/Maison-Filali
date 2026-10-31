<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Messages WhatsApp envoyés par l'API officielle WhatsApp Cloud (Meta for Developers) à chaque nouvelle commande :
 *  - une alerte à l'équipe (gérant, confirmatrices…) ;
 *  - (option) une confirmation au client.
 * Réglages : Admin → Paramètres → « WhatsApp (Meta) ».
 *
 * Meta n'autorise que des « modèles de message » approuvés pour écrire en premier à quelqu'un.
 * Modèle alerte équipe (6 variables) :
 *   {{1}} n° de commande · {{2}} articles · {{3}} total · {{4}} client · {{5}} téléphone · {{6}} ville / adresse
 * Modèle confirmation client (3 variables) :
 *   {{1}} prénom · {{2}} n° de commande · {{3}} total
 */
class WhatsAppAlert
{
    public const TEAM_TEMPLATE_TEXT = "Nouvelle commande {{1}} sur le site Maison Filali.\nArticles : {{2}}\nTotal à encaisser : {{3}}\nClient : {{4}}\nTéléphone : {{5}}\nVille / adresse : {{6}}\nMerci de confirmer la commande depuis l'espace administrateur.";

    public const CLIENT_TEMPLATE_TEXT = "Bonjour {{1}}, merci pour votre commande {{2}} chez Maison Filali. Total : {{3}}, à payer à la livraison. Notre équipe vous contacte sous 24 h pour la confirmer.";

    public static function configured(): bool
    {
        return filled(config('shop.wa_phone_id')) && filled(config('shop.wa_token'));
    }

    /** Numéros de l'équipe qui reçoivent l'alerte. @return array<int, array{name: ?string, phone: string, active: bool}> */
    public static function recipients(bool $activeOnly = true): array
    {
        $list = config('shop.order_alerts');
        $list = is_string($list) ? json_decode($list, true) : $list;

        return collect(is_array($list) ? $list : [])
            ->filter(fn ($r) => ! empty($r['phone']) && (! $activeOnly || ($r['active'] ?? true)))
            ->values()->all();
    }

    /** Appelé après l'enregistrement d'une commande. L'envoi se fait après l'affichage de la page « Merci » au client. */
    /**
     * Ne lève JAMAIS d'exception : une panne WhatsApp (Meta injoignable, jeton expiré, réglage incorrect…)
     * ne doit pas empêcher la commande. Les erreurs sont seulement écrites dans storage/logs/laravel.log.
     */
    public static function newOrder(Order $order): void
    {
        try {
            if (! self::configured()) {
                return;
            }
            $recipients = self::recipients();
            $clientTemplate = trim((string) config('shop.wa_client_template'));
            if (! $recipients && $clientTemplate === '') {
                return;
            }
            $job = fn () => self::sendForOrder($order, $recipients, $clientTemplate);

            // Après l'envoi de la page au client ; sinon (ancienne version de Laravel) en fin de requête
            function_exists('Illuminate\Support\defer') ? defer($job) : app()->terminating($job);
        } catch (Throwable $e) {
            self::report('préparation', $order, $e);
        }
    }

    /** Envoie les messages un par un : l'échec d'un numéro n'empêche pas les suivants. */
    private static function sendForOrder(Order $order, array $recipients, string $clientTemplate): void
    {
        try {
            $template = trim((string) config('shop.wa_template')) ?: 'maison_filali_commande';
            $params = self::teamParams($order);
        } catch (Throwable $e) {
            self::report('contenu du message', $order, $e);

            return;
        }

        $start = microtime(true);
        $tooLong = fn () => microtime(true) - $start > 8; // budget total : au-delà de 8 s, on n’envoie plus rien

        foreach ($recipients as $r) {
            if ($tooLong()) {
                Log::warning('WhatsApp : envois restants annulés (Meta trop lent)', ['commande' => $order->number]);

                return;
            }
            try {
                if ($error = self::sendTemplate((string) $r['phone'], $template, $params)) {
                    Log::warning('Alerte WhatsApp (équipe) non envoyée', ['commande' => $order->number, 'numero' => $r['phone'], 'erreur' => $error]);
                }
            } catch (Throwable $e) {
                self::report('alerte équipe', $order, $e);
            }
        }

        if ($clientTemplate !== '' && ! $tooLong()) {
            try {
                if ($error = self::sendTemplate((string) $order->phone, $clientTemplate, self::clientParams($order))) {
                    Log::warning('Confirmation WhatsApp (client) non envoyée', ['commande' => $order->number, 'erreur' => $error]);
                }
            } catch (Throwable $e) {
                self::report('confirmation client', $order, $e);
            }
        }
    }

    private static function report(string $step, Order $order, Throwable $e): void
    {
        try {
            Log::error('WhatsApp : erreur ignorée ('.$step.')', ['commande' => $order->number ?? null, 'erreur' => $e->getMessage(), 'fichier' => $e->getFile().':'.$e->getLine()]);
        } catch (Throwable) {
            // même le journal est indisponible : on abandonne en silence
        }
    }

    /** @return array<int, string> */
    public static function teamParams(Order $order): array
    {
        $order->loadMissing('items');
        $items = $order->items->map(function ($item) {
            $details = array_filter([$item->is_custom ? 'sur mesure' : null, $item->color_name, $item->size_name]);

            return $item->quantity.' × '.$item->product_name.($details ? ' ('.implode(', ', $details).')' : '');
        })->implode(' + ');

        return [
            $order->number,
            $items ?: '—',
            money($order->total),
            $order->customer_name,
            $order->phone,
            $order->city.($order->address ? ' — '.$order->address : ''),
        ];
    }

    /** @return array<int, string> */
    public static function clientParams(Order $order): array
    {
        $first = mb_convert_case(mb_strtolower(strtok(trim($order->customer_name), ' ') ?: $order->customer_name), MB_CASE_TITLE);

        return [$first, $order->number, money($order->total)];
    }

    /** Valeurs d'exemple pour le bouton « Tester ». @return array<int, string> */
    public static function sampleTeamParams(): array
    {
        return ['MF-TEST', '1 × Caftan (Vert, M)', money(1650), 'Client test', '06 00 00 00 00', 'Casablanca'];
    }

    /**
     * Envoie un modèle de message approuvé. Retourne null si Meta l'a accepté, sinon le message d'erreur.
     * Le modèle « hello_world » (fourni par Meta, en anglais, sans variable) sert à tester les identifiants.
     *
     * @param  array<int, string>  $params
     */
    public static function sendTemplate(string $phone, string $template, array $params = []): ?string
    {
        try {
            return self::doSend($phone, $template, $params);
        } catch (Throwable $e) {
            return 'Erreur inattendue : '.$e->getMessage();
        }
    }

    /** @param  array<int, string>  $params */
    private static function doSend(string $phone, string $template, array $params): ?string
    {
        if (! self::configured()) {
            return 'Indiquez l’identifiant du numéro et le jeton d’accès (Paramètres → WhatsApp).';
        }
        $template = trim($template);
        $isHello = $template === 'hello_world';
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => self::international($phone),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $isHello ? 'en_US' : (trim((string) config('shop.wa_lang')) ?: 'fr')],
            ],
        ];
        if (! $isHello && $params) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => self::clean($v)], array_values($params)),
            ]];
        }

        try {
            // Délais courts : si Meta ne répond pas, on abandonne vite (la commande ne doit jamais rester bloquée)
            $res = Http::connectTimeout(3)->timeout(6)
                ->withToken(trim((string) config('shop.wa_token')))
                ->post('https://graph.facebook.com/'.(config('shop.wa_api_version') ?: 'v23.0').'/'.trim((string) config('shop.wa_phone_id')).'/messages', $payload);
        } catch (Throwable $e) {
            return 'Meta injoignable ('.$e->getMessage().')';
        }

        if ($res->successful() && $res->json('messages.0.id')) {
            return null;
        }

        return self::explain($res->json('error') ?? [], $res->status());
    }

    /** Traduit les erreurs Meta les plus fréquentes. */
    private static function explain(array $error, int $status): string
    {
        $code = (int) ($error['code'] ?? 0);
        $details = $error['error_data']['details'] ?? $error['message'] ?? ('réponse inattendue (HTTP '.$status.')');
        $hint = match (true) {
            $code === 190 => 'Jeton d’accès invalide ou expiré (le jeton temporaire expire après 24 h : créez un jeton permanent avec un utilisateur système).',
            $code === 131030 => 'Ce numéro n’est pas dans la liste des destinataires autorisés (mode test) : ajoutez-le dans Meta → WhatsApp → Configuration de l’API → « À ».',
            $code === 132001 => 'Modèle introuvable : vérifiez le nom et la langue, et qu’il est bien « Approuvé » dans Meta.',
            $code === 132000 => 'Le nombre de variables ne correspond pas au modèle approuvé.',
            $code === 131026 => 'Ce numéro n’a pas WhatsApp ou ne peut pas recevoir le message.',
            $code === 131047 => 'Message libre impossible : utilisez un modèle approuvé.',
            in_array($code, [100, 33], true) => 'Identifiant du numéro (Phone number ID) incorrect, ou jeton sans accès à ce numéro.',
            $code === 131031 => 'Compte WhatsApp Business bloqué ou non vérifié.',
            in_array($code, [4, 80007, 130429, 131048, 131056], true) => 'Trop de messages envoyés : réessayez plus tard.',
            default => null,
        };

        return mb_strimwidth(($hint ? $hint.' — ' : '').'Meta : '.$details.($code ? ' (code '.$code.')' : ''), 0, 400, '…');
    }

    /** Meta refuse les retours à la ligne, tabulations et plus de 4 espaces de suite dans une variable. */
    private static function clean(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return mb_substr($value !== '' ? $value : '—', 0, 900);
    }

    /** 06 12 34 56 78 → 212612345678. Les numéros étrangers (+33…, 0033…) sont gardés. */
    public static function international(string $phone): string
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (! str_starts_with($raw, '+') && str_starts_with($digits, '0')) {
            $digits = '212'.substr($digits, 1);
        }

        return $digits;
    }
}
