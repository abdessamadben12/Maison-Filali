<?php

namespace App\Support;

use App\Models\Size;

/**
 * Pages de contenu du site. Les textes marqués [à compléter] doivent être
 * remplis avec les informations officielles de Maison Filali.
 */
class Pages
{
    public static function find(string $slug): ?array
    {
        $phone = e(config('shop.phone'));

        return match ($slug) {
            'notre-histoire' => [
                'eyebrow' => 'Maison Filali',
                'title' => 'Notre histoire',
                'html' => '<p>Maison Filali propose des vêtements traditionnels marocains : caftans, takchitas, djellabas et babouches, faits main. Depuis plus d’un an, nos clientes nous retrouvent sur Instagram et WhatsApp ; ce site leur permet désormais de commander directement, partout au Maroc, avec paiement à la livraison.</p>
<h2>Nos créations</h2><p>Broderies, finitions sfifa et aakad, couleurs choisies avec soin : chaque pièce est pensée pour les fêtes comme pour le quotidien. La plupart de nos modèles peuvent aussi être réalisés sur mesure.</p>
<p>[Histoire de la marque à compléter par Maison Filali]</p>
<h2>Nous rendre visite</h2><p>'.nl2br(e(config('shop.address'))).'<br>'.e(config('shop.hours')).'<br>Téléphone / WhatsApp : '.$phone.'</p>',
            ],
            'comment-commander' => [
                'eyebrow' => 'Aide',
                'title' => 'Comment commander',
                'html' => '<ul>
<li><strong>Choisissez</strong> votre modèle, la couleur et la taille sur la page produit, puis cliquez sur « Commander ».</li>
<li><strong>Indiquez vos coordonnées</strong> : nom, téléphone et ville. Aucun compte n’est nécessaire.</li>
<li><strong>Notre équipe confirme</strong> votre commande sous 24 h. Vous suivez son statut sur le site (page « Suivre ma commande » ou votre espace client).</li>
<li><strong>Payez à la livraison</strong>, en espèces, à réception de votre colis.</li>
</ul><p>Une question ? Écrivez-nous sur WhatsApp au '.$phone.'.</p>',
            ],
            'guide-des-tailles' => [
                'eyebrow' => 'Aide',
                'title' => 'Guide des tailles',
                'html' => self::sizeGuide(),
            ],
            'livraison-retours' => [
                'eyebrow' => 'Aide',
                'title' => 'Livraison & retours',
                'html' => '<h2>Livraison</h2><p>Nous livrons partout au Maroc en 24 à 72 h après confirmation de la commande. Le paiement se fait en espèces à la livraison. Frais de livraison : [à compléter].</p>
<h2>Échanges et retours</h2><p>Vous pouvez échanger ou retourner un article sous 7 jours après réception, s’il n’a pas été porté et qu’il est rendu dans son emballage d’origine. Les pièces réalisées sur mesure ne sont ni reprises ni échangées. [Conditions de remboursement à compléter]</p>
<p>Pour demander un échange : '.$phone.' (téléphone ou WhatsApp).</p>',
            ],
            'mentions-legales' => [
                'eyebrow' => 'Informations',
                'title' => 'Mentions légales',
                'html' => '<p><strong>Éditeur du site :</strong> Maison Filali — [raison sociale, forme juridique et capital à compléter]<br>Adresse : '.nl2br(e(config('shop.address'))).'<br>RC : [à compléter] · ICE : [à compléter] · IF : [à compléter]<br>Téléphone : '.$phone.'</p>
<p><strong>Hébergement :</strong> [nom et adresse de l’hébergeur à compléter]</p>',
            ],
            'confidentialite' => [
                'eyebrow' => 'Informations',
                'title' => 'Confidentialité',
                'html' => '<p>Les informations que vous donnez lors d’une commande (nom, téléphone, ville, adresse, e-mail si vous créez un compte) servent uniquement à traiter et livrer votre commande. Elles ne sont ni vendues ni partagées, sauf avec le livreur pour la livraison.</p>
<p>Conformément à la loi 09-08, vous pouvez demander l’accès, la rectification ou la suppression de vos données en nous contactant au '.$phone.'. [Numéro de déclaration CNDP à compléter]</p>',
            ],
            'cgv' => [
                'eyebrow' => 'Informations',
                'title' => 'Conditions générales de vente',
                'html' => '<h2>Commande</h2><p>Toute commande passée sur le site est enregistrée puis confirmée par Maison Filali, par téléphone ou via le site, sous réserve de disponibilité.</p>
<h2>Prix et paiement</h2><p>Les prix sont indiqués en dirhams (DH) TTC. Le paiement s’effectue en espèces à la livraison.</p>
<h2>Livraison</h2><p>Livraison partout au Maroc en 24 à 72 h après confirmation.</p>
<h2>Échanges</h2><p>Échange possible sous 7 jours après réception, article non porté et dans son emballage. Les créations sur mesure ne sont pas échangeables.</p>
<p>[Conditions complètes à valider par Maison Filali]</p>',
            ],
            default => null,
        };
    }

    private static function sizeGuide(): string
    {
        $html = '<p>Mesurez-vous sans serrer le mètre ruban. En cas de doute entre deux tailles, prenez la plus grande ou contactez-nous sur WhatsApp.</p>';
        $clothes = Size::where('type', 'vetement')->orderBy('position')->get();
        if ($clothes->count()) {
            $html .= '<h2>Caftans, takchitas, djellabas</h2><table><tr><th>Taille</th><th>Mesure</th></tr>';
            foreach ($clothes as $s) {
                $html .= '<tr><td>'.e($s->name).'</td><td>'.e($s->guide ?: '—').'</td></tr>';
            }
            $html .= '</table>';
        }
        $shoes = Size::where('type', 'pointure')->orderBy('position')->get();
        if ($shoes->count()) {
            $html .= '<h2>Babouches</h2><table><tr><th>Pointure</th><th>Longueur du pied</th></tr>';
            foreach ($shoes as $s) {
                $html .= '<tr><td>'.e($s->name).'</td><td>'.e($s->guide ?: '—').'</td></tr>';
            }
            $html .= '</table>';
        }

        return $html.'<p>Toutes nos pièces peuvent aussi être réalisées sur mesure.</p>';
    }
}
