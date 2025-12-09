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
        'base_url' => getenv('DESCOBERTA_BASE_URL') ?: 'https://tu-dominio-descoberta.com',
        'consumer_key' => getenv('DESCOBERTA_CONSUMER_KEY') ?: '',
        'consumer_secret' => getenv('DESCOBERTA_CONSUMER_SECRET') ?: '',
        'basic_user' => getenv('DESCOBERTA_BASIC_USER') ?: '',
        'basic_password' => getenv('DESCOBERTA_BASIC_PASSWORD') ?: '',
        'categories' => [
            // Rellena los IDs reales de WooCommerce
            'activitat-de-dia' => null,
            'centre-interes' => null,
            'cases-de-colonies' => null,
        ],
    ],
    'can-pere' => [
        'name' => 'Can Pere',
        'base_url' => getenv('CANPERE_BASE_URL') ?: 'https://tu-dominio-can-pere.com',
        'consumer_key' => getenv('CANPERE_CONSUMER_KEY') ?: '',
        'consumer_secret' => getenv('CANPERE_CONSUMER_SECRET') ?: '',
        'basic_user' => getenv('CANPERE_BASIC_USER') ?: '',
        'basic_password' => getenv('CANPERE_BASIC_PASSWORD') ?: '',
        'categories' => [
            'activitat-de-dia' => null,
            'centre-interes' => null,
            'cases-de-colonies' => null,
        ],
    ],
    'cal-mata' => [
        'name' => 'Cal Mata',
        'base_url' => getenv('CALMATA_BASE_URL') ?: 'https://tu-dominio-cal-mata.com',
        'consumer_key' => getenv('CALMATA_CONSUMER_KEY') ?: '',
        'consumer_secret' => getenv('CALMATA_CONSUMER_SECRET') ?: '',
        'basic_user' => getenv('CALMATA_BASIC_USER') ?: '',
        'basic_password' => getenv('CALMATA_BASIC_PASSWORD') ?: '',
        'categories' => [
            'activitat-de-dia' => null,
            'centre-interes' => null,
            'cases-de-colonies' => null,
        ],
    ],
    'can-foix' => [
        'name' => 'Can Foix',
        'base_url' => getenv('CANFOIX_BASE_URL') ?: 'https://tu-dominio-can-foix.com',
        'consumer_key' => getenv('CANFOIX_CONSUMER_KEY') ?: '',
        'consumer_secret' => getenv('CANFOIX_CONSUMER_SECRET') ?: '',
        'basic_user' => getenv('CANFOIX_BASIC_USER') ?: '',
        'basic_password' => getenv('CANFOIX_BASIC_PASSWORD') ?: '',
        'categories' => [
            'activitat-de-dia' => null,
            'centre-interes' => null,
            'cases-de-colonies' => null,
        ],
    ],
    'el-ginebro' => [
        'name' => 'El Ginebró',
        'base_url' => getenv('GINEBRO_BASE_URL') ?: 'https://tu-dominio-el-ginebro.com',
        'consumer_key' => getenv('GINEBRO_CONSUMER_KEY') ?: '',
        'consumer_secret' => getenv('GINEBRO_CONSUMER_SECRET') ?: '',
        'basic_user' => getenv('GINEBRO_BASIC_USER') ?: '',
        'basic_password' => getenv('GINEBRO_BASIC_PASSWORD') ?: '',
        'categories' => [
            'activitat-de-dia' => null,
            'centre-interes' => null,
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
