<?php
return array(
	// Kredit blokunu aktiv/deaktiv et
	'enabled' => true,

	// Ayliq mebleg metni
	'label' => 'Ayliq:',

	// Ay => faiz (%)
	// Formula: (qiymet * (1 + faiz / 100)) / ay
	'plans' => array(
		2 => 3.2,
		3 => 4.2,
		6 => 6,
		12 => 15,
		18 => 0,
	),
);
