<?php
return array(
	// Kredit blokunu aktiv/deaktiv et
	'enabled' => false,

	// Ayliq mebleg metni
	'label' => 'Ayliq:',

	// Ay => faiz (%)
	//
	// Formula: (qiymet * (1 + faiz / 100)) / ay
	//
	// Misal: qiymet = 696 AZN, 12 ay, faiz = -6.61%
	//   (696 * (1 + (-6.61 / 100))) / 12
	//   = (696 * 0.9339) / 12
	//   = 650.0 / 12
	//   = 54.17 AZN/ay
	//
	// Menfi faiz => endirim (qiymeti azaldır)
	// Musbat faiz => artım  (qiymeti artırır)
	//
	'plans' => array(
		2  => -14,
		3  => -13.2,
		6  => -11.7,
		12 => -6.61,
		18 => 0,
	),
);
