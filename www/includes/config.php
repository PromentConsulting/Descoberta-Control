<?php
// Configuración general de la app

define('ADMIN_SEED', [
    'username' => 'admin',
    'password_hash' => '$2y$12$I.wlt8zsHHRS4hgAv1FLpOT48lDxEhmES2NMMIy.k2O8b/jo2/Evq',
    'role' => 'admin',
]);

// Configuración de las 5 webs
$SITE_APIS = [
    'descoberta' => [
        'name' => 'Descoberta',
        'base_url' => getenv('DESCOBERTA_BASE_URL') ?: 'https://descobertaweb.promentconsulting.com/',
        'consumer_key' => getenv('DESCOBERTA_CONSUMER_KEY') ?: 'ck_41b062a032bc5ec02b8c63080a369dfa8d65bdc4',
        'consumer_secret' => getenv('DESCOBERTA_CONSUMER_SECRET') ?: 'cs_13c57c5475dfde80a3186078d3733d63412a01b9',
        'basic_user' => getenv('DESCOBERTA_BASIC_USER') ?: 'ertaweb',
        'basic_password' => getenv('DESCOBERTA_BASIC_PASSWORD') ?: 'oPkw 5fQp vRlg nKlZ 2Sbw LIJw',
        'categories' => [
            // Rellena los IDs reales de WooCommerce
            'activitat-de-dia' => 55,
            'centre-interes' => 56,
            'cases-de-colonies' => 28,
        ],
    ],
    'can-pere' => [
        'name' => 'Can Pere',
        'base_url' => getenv('CANPERE_BASE_URL') ?: 'https://canperedescoberta.promentconsulting.com/',
        'consumer_key' => getenv('CANPERE_CONSUMER_KEY') ?: 'ck_fc3c213be2780b0a2cc05895cace7871cc86f2a2',
        'consumer_secret' => getenv('CANPERE_CONSUMER_SECRET') ?: 'cs_f4097f7db551014de842d412a1e985d3375b47e2',
        'basic_user' => getenv('CANPERE_BASIC_USER') ?: 'ertaweb',
        'basic_password' => getenv('CANPERE_BASIC_PASSWORD') ?: '9FBy coWG AQdZ VUzq CXLx 9nN0',
        'categories' => [
            'activitat-de-dia' => 25,
            'centre-interes' => 26,
            'cases-de-colonies' => null,
        ],
    ],
    'cal-mata' => [
        'name' => 'Cal Mata',
        'base_url' => getenv('CALMATA_BASE_URL') ?: 'https://escolesdescoberta.promentconsulting.com/',
        'consumer_key' => getenv('CALMATA_CONSUMER_KEY') ?: 'ck_d2983c1a279c7589e9fbb5536c7e04ba3a732c34',
        'consumer_secret' => getenv('CALMATA_CONSUMER_SECRET') ?: 'cs_4fa3088f482fba1d73e74552158234faf886c1e0',
        'basic_user' => getenv('CALMATA_BASIC_USER') ?: 'ertaweb',
        'basic_password' => getenv('CALMATA_BASIC_PASSWORD') ?: 'HlEF MLDy DcqV 9Bmn 1HGX 0db3',
        'categories' => [
            'activitat-de-dia' => 25,
            'centre-interes' => 26,
            'cases-de-colonies' => null,
        ],
    ],
    'can-foix' => [
        'name' => 'Can Foix',
        'base_url' => getenv('CANFOIX_BASE_URL') ?: 'https://canfoixdescoberta.promentconsulting.com/',
        'consumer_key' => getenv('CANFOIX_CONSUMER_KEY') ?: 'ck_c83e9caa7ea38cd854991226d36715441e2389d7',
        'consumer_secret' => getenv('CANFOIX_CONSUMER_SECRET') ?: 'cs_e500b4f26aa1e16b10a7f852ae9cca06f2623850',
        'basic_user' => getenv('CANFOIX_BASIC_USER') ?: 'ertaweb',
        'basic_password' => getenv('CANFOIX_BASIC_PASSWORD') ?: 'ffpc SpYp 2Tqj TLMf h4o6 DyZI',
        'categories' => [
            'activitat-de-dia' => 25,
            'centre-interes' => 26,
            'cases-de-colonies' => null,
        ],
    ],
    'el-ginebro' => [
        'name' => 'El Ginebró',
        'base_url' => getenv('GINEBRO_BASE_URL') ?: 'https://elginebrodescoberta.promentconsulting.com/',
        'consumer_key' => getenv('GINEBRO_CONSUMER_KEY') ?: 'ck_5af258f2656f422b586c8ee2a1d8865b42bc2181',
        'consumer_secret' => getenv('GINEBRO_CONSUMER_SECRET') ?: 'cs_2cd52e23f1f308576beb755f7866cf426830739a',
        'basic_user' => getenv('GINEBRO_BASIC_USER') ?: 'ertaweb',
        'basic_password' => getenv('GINEBRO_BASIC_PASSWORD') ?: 'KOpY YdHn 8Sfn Kkdd XgBQ gqMZ',
        'categories' => [
            'activitat-de-dia' => 25,
            'centre-interes' => 26,
            'cases-de-colonies' => null,
        ],
    ],
];

$CASE_SPECIAL_MAPPING = [
    395 => 'can-foix',
    512 => 'cal-mata',
    579 => 'can-pere',
    587 => 'el-ginebro',
];

$ACF_FIELD_KEYS = [
    'activitats' => [
        'cicles' => 'cicles',
        'categoria' => 'categoria',
        'continguts' => 'continguts',
        'programa' => 'programa',
        'preus' => 'preus',
        'inclou' => 'inclou',
    ],
    'centres' => [
        'competencies' => 'competencies',
        'metodologia' => 'metodologia',
        'titol_programa_1' => 'titol_programa_1',
        'descripcio_programa_1' => 'descripcio_programa_1',
        'titol_programa_2' => 'titol_programa_2',
        'descripcio_programa_2' => 'descripcio_programa_2',
        'titol_programa_3' => 'titol_programa_3',
        'descripcio_programa_3' => 'descripcio_programa_3',
        'titol_programa_4' => 'titol_programa_4',
        'descripcio_programa_4' => 'descripcio_programa_4',
        'titol_programa_5' => 'titol_programa_5',
        'descripcio_programa_5' => 'descripcio_programa_5',
        'preus' => 'preus',
        'inclou' => 'inclou',
        'altres_activitats' => 'altres_activitats',
        'cases_on_es_pot_fer' => 'cases_on_es_pot_fer',
        'altres_propostes' => 'altres_propostes',
    ],
    'url' => 'url',
];

$CICLES_OPTIONS = [
    'Infantil', 'Cicle Inicial', 'Cicle Mitjà', 'Cicle Superior', 'Educació infantil', 'ESO', 'Baxtillerat'
];

$CATEGORIES_OPTIONS = [
    'Propostes a l\'aula', 'Cuina', 'Apicultura', 'Treball de camp', 'Natura', 'Granja i vida al camp',
    'Contres i llegendes', 'Tradició', 'Aventura', 'Expressió corporal i artística', 'Mar', 'Història',
    'Dinàmiques', 'Sostenibilitat', 'Cures personals', 'Participació i vida comunitària'
];
