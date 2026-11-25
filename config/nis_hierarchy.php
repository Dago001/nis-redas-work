<?php

/**
 * Authoritative Nigeria Immigration Service (NIS) Zonal Commands & Formations Hierarchy.
 * Single source of truth for ReportingUnit seeding, user provisioning, and Zonal consolidation.
 */
return [
    'zones' => [
        'ZONE-A' => [
            'name' => 'Zone A HQ Ikeja',
            'commands' => [
                'LASC'  => 'Lagos State Command',
                'OGSC'  => 'Ogun State Command',
                'LASPC' => 'Lagos Seaport and Marine Command',
                'SEME'  => 'Seme Border Command',
                'IDBC'  => 'Idiroko Border Command',
                'MMIA'  => 'Murtala Muhammed International Airport',
                'LABPC' => 'Lagos State Border Patrol Command, Seme',
                'LAPC'  => 'Lagos Passport Command',
            ],
        ],
        'ZONE-B' => [
            'name' => 'Zone B HQ Kaduna',
            'commands' => [
                'KNSC'  => 'Kano State Command',
                'KDSC'  => 'Kaduna State Command',
                'KTSC'  => 'Katsina State Command',
                'ZMSC'  => 'Zamfara State Command',
                'SOSC'  => 'Sokoto State Command',
                'JGSC'  => 'Jigawa State Command',
                'MAKIA' => 'Mallam Aminu Kano International Airport Command',
                'ILBC'  => 'Illela Border Command',
                'JIBC'  => 'Jibia Border Command',
                'ITSK'  => 'Immigration Training School Kano',
                'ICSC'  => 'Immigration Command and Staff College',
            ],
        ],
        'ZONE-C' => [
            'name' => 'Zone C HQ Bauchi',
            'commands' => [
                'ADSC' => 'Adamawa State Command',
                'BASC' => 'Bauchi State Command',
                'BOSC' => 'Borno State Command',
                'GMSC' => 'Gombe State Command',
                'PLSC' => 'Plateau State Command',
                'YBSC' => 'Yobe State Command',
            ],
        ],
        'ZONE-D' => [
            'name' => 'Zone D HQ Minna',
            'commands' => [
                'FCTC' => 'FCT Command',
                'NGSC' => 'Niger State Command',
                'KBSC' => 'Kebbi State Command',
                'RMAT' => 'Regional Migration Academy, Tuga',
                'KWSC' => 'Kwara State Command',
            ],
        ],
        'ZONE-E' => [
            'name' => 'Zone E HQ Owerri',
            'commands' => [
                'IMSC'   => 'Imo State Command',
                'RVSC'   => 'Rivers State Command',
                'CRSC'   => 'Cross River State Command',
                'EBSC'   => 'Ebonyi State Command',
                'AKSC'   => 'Akwa Ibom State Command',
                'NITSOL' => 'Nigeria Immigration Training School Orlu',
                'RVMC'   => 'Rivers Marine Command Onne',
                'MFBC'   => 'Mfum Border Command',
                'NITSA'  => 'Nigeria Immigration Training School Ahoada',
            ],
        ],
        'ZONE-F' => [
            'name' => 'Zone F HQ Ibadan',
            'commands' => [
                'OYSC' => 'Oyo State Command',
                'EKSC' => 'Ekiti State Command',
                'ODSC' => 'Ondo State Command',
                'OSSC' => 'Osun State Command',
            ],
        ],
        'ZONE-G' => [
            'name' => 'Zone G HQ Benin',
            'commands' => [
                'EDSC' => 'Edo State Command',
                'ANSC' => 'Anambra State Command',
                'DTSC' => 'Delta State Command',
                'ENSC' => 'Enugu State Command',
                'BYSC' => 'Bayelsa State Command',
            ],
        ],
        'ZONE-H' => [
            'name' => 'Zone H HQ Makurdi',
            'commands' => [
                'NASC' => 'Nasarawa State Command',
                'BNSC' => 'Benue State Command',
                'KGSC' => 'Kogi State Command',
                'TRSC' => 'Taraba State Command',
            ],
        ],
    ],

    // 2-letter ISO/NIS state code to official Zone mapping (matches the authoritative table above)
    'state_to_zone' => [
        'LA' => 'ZONE-A', 'OG' => 'ZONE-A',
        'KN' => 'ZONE-B', 'KD' => 'ZONE-B', 'KT' => 'ZONE-B', 'ZA' => 'ZONE-B', 'SO' => 'ZONE-B', 'JI' => 'ZONE-B',
        'AD' => 'ZONE-C', 'BA' => 'ZONE-C', 'BO' => 'ZONE-C', 'GO' => 'ZONE-C', 'PL' => 'ZONE-C', 'YO' => 'ZONE-C',
        'FC' => 'ZONE-D', 'NI' => 'ZONE-D', 'KE' => 'ZONE-D', 'KW' => 'ZONE-D',
        'IM' => 'ZONE-E', 'RI' => 'ZONE-E', 'CR' => 'ZONE-E', 'EB' => 'ZONE-E', 'AK' => 'ZONE-E', 'AB' => 'ZONE-E',
        'OY' => 'ZONE-F', 'EK' => 'ZONE-F', 'ON' => 'ZONE-F', 'OS' => 'ZONE-F',
        'ED' => 'ZONE-G', 'AN' => 'ZONE-G', 'DE' => 'ZONE-G', 'EN' => 'ZONE-G', 'BY' => 'ZONE-G',
        'NA' => 'ZONE-H', 'BE' => 'ZONE-H', 'KO' => 'ZONE-H', 'TA' => 'ZONE-H',
    ],
];
