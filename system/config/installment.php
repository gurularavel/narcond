<?php
return array(
	// Kredit blokunu aktiv/deaktiv et
	'enabled' => true,

	// Ayliq mebleg metni
	'label' => 'Ayliq:',

	// Ay => faiz (%)
	// Formula: (qiymet * (1 + faiz / 100)) / ay
	'plans' => array(
		3 => 100,
		6 => 10,
		9 => 10,
	),
);
