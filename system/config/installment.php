<?php
/**
 * Kredit (taksit) konfiqurasiyası
 *
 * Formula: (qiymet * (1 + faiz / 100)) / ay
 *   faiz = 0   → faizsiz taksit (qiymət bölünür)
 *   faiz > 0   → artım (banka faizi)
 *   faiz < 0   → endirim
 *
 * Logolar: image/catalog/banks/ qovluğuna yükləyin
 *   Tövsiyə olunan ölçü: 120×40 px, PNG (şəffaf fon)
 */
return array(

    // Kredit bölməsini göstər / gizlət
    'enabled' => true,

    // Banklar
    'banks' => array(


        'birbank' => array(
            'name' => 'Birbank',
            'logo' => 'image/catalog/banks/birbank.png',
            'plans' => array(
                3  => 0,
                6  => 0,
                12 => 0,
                18 => 0,
            ),
        ),

        'tamkart' => array(
            'name' => 'TamKart',
            'logo' => 'image/catalog/banks/tamkart.png',
            'plans' => array(
                3  => 0,
                6  => 0,
                9  => 0,
                12 => 0,
                18 => 0,
                24 => 0,
            ),
        ),

    ),
);
