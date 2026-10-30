<?php

return [
    // Valeurs par défaut. Elles se modifient depuis Admin → Paramètres (prioritaires sur ce fichier).
    'name' => 'Maison Filali',
    'phone' => env('SHOP_PHONE', '06 78 20 88 68'),
    'whatsapp' => env('SHOP_WHATSAPP', '212678208868'),
    'instagram' => '@la_maison_filalii',
    'instagram_url' => 'https://www.instagram.com/la_maison_filalii/',
    // Laisser vide (null) pour masquer l'icône sur le site
    'facebook_url' => env('SHOP_FACEBOOK_URL'),
    'tiktok_url' => env('SHOP_TIKTOK_URL'),
    'domain' => 'https://maisonfilali.ma',
    'address' => "Roches Noires, Maamoura, Ennakhil\nLot L1 — Casablanca, Maroc",
    'address_short' => "Roches Noires, Maamoura,\nEnnakhil — Casablanca",
    'hours' => 'Lundi – Samedi · 10h – 20h',
    'hours_short' => 'Lun – Sam · 10h – 20h',
    'maps_url' => 'https://maps.google.com/?cid=2549449862841452867',
    // Carte Google Maps intégrée (adresse « src » de l'iframe). Modifiable dans Admin → Paramètres.
    'maps_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d53222.234114533545!2d-7.783073851367175!3d33.51725400000001!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xda62b001dc39fa5%3A0x2361772487554943!2sMaison%20filali!5e0!3m2!1sfr!2sma!4v1793365428807!5m2!1sfr!2sma',

    // Nombre maximum de catégories dans le menu du haut (au-delà, le menu ne tient plus à côté du logo)
    'menu_max' => 2,

    // Seuil « stock faible » (en pièces)
    'low_stock' => 2,

    // Version des fichiers CSS/JS (à changer pour vider le cache des navigateurs)
    'assets_version' => '1.4.5',

    'cities' => [
        'Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 'Oujda', 'Kénitra', 'Tétouan',
        'Salé', 'Mohammedia', 'El Jadida', 'Settat', 'Béni Mellal', 'Nador', 'Safi', 'Khouribga', 'Témara',
        'Laâyoune', 'Dakhla', 'Essaouira', 'Ouarzazate', 'Errachidia', 'Taza', 'Berrechid', 'Khémisset',
        'Larache', 'Guelmim', 'Ifrane', 'Autre ville',
    ],
];
