<?php

unset($CFG);
global $CFG;
$CFG = new stdClass();

$env = static function(string $name, string $default = ''): string {
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
};
$boolenv = static function(string $name, bool $default = false) use ($env): bool {
    return filter_var($env($name, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
};

$CFG->dbtype = $env('IOMAD_DB_TYPE', 'pgsql');
$CFG->dblibrary = 'native';
$CFG->dbhost = $env('IOMAD_DB_HOST', 'db');
$CFG->dbname = $env('IOMAD_DB_NAME', $env('POSTGRES_DB', 'iomad'));
$CFG->dbuser = $env('IOMAD_DB_USER', $env('POSTGRES_USER', 'iomad'));
$CFG->dbpass = $env('IOMAD_DB_PASSWORD', $env('POSTGRES_PASSWORD'));
$CFG->prefix = $env('IOMAD_DB_PREFIX', 'mdl_');
$CFG->dboptions = [
    'dbpersist' => false,
    'dbport' => $env('IOMAD_DB_PORT', '5432'),
    'dbsocket' => '',
];

$CFG->wwwroot = rtrim($env('IOMAD_WWWROOT', 'http://localhost:18080'), '/');
$CFG->dataroot = $env('IOMAD_DATAROOT', '/var/www/iomaddata');
$CFG->admin = 'admin';
$CFG->directorypermissions = 02770;
$CFG->reverseproxy = $boolenv('IOMAD_REVERSEPROXY');
$CFG->sslproxy = $boolenv('IOMAD_SSLPROXY');

$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = $env('IOMAD_REDIS_HOST', 'redis');
$CFG->session_redis_port = (int)$env('IOMAD_REDIS_PORT', '6379');
$CFG->session_redis_database = (int)$env('IOMAD_REDIS_DATABASE', '0');
$CFG->session_redis_prefix = $env('IOMAD_REDIS_PREFIX', 'iomad_session_');
$CFG->session_redis_acquire_lock_timeout = 120;
$CFG->session_redis_lock_expire = 7200;
if ($boolenv('IOMAD_REDIS_TLS')) {
    $CFG->session_redis_encrypt = true;
}

$awsendpoint = $env('IOMAD_AWS_ENDPOINT');
if ($awsendpoint !== '') {
    $CFG->local_aws_s3_client_options = [
        'version' => 'latest',
        'region' => $env('AWS_REGION', 'us-east-1'),
        'endpoint' => rtrim($awsendpoint, '/'),
        'use_path_style_endpoint' => true,
    ];
}

require_once(__DIR__ . '/lib/setup.php');
