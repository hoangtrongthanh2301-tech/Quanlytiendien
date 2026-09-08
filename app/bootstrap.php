<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Container;
use App\Core\Database;
use App\Repositories\ChiSoDienRepository;
use App\Repositories\BlockchainBatchRepository;
use App\Services\HashService;
use App\Services\MerkleTreeService;
use App\Services\BlockchainService;
use App\Services\VerificationService;
use App\Services\ImportService;
use App\Controllers\ImportController;
use App\Controllers\BlockchainController;
use App\Controllers\DashboardController;

Autoloader::register();

$container = new Container();
$database = new Database($conn);

$container->set('database', $database);
$container->set('hash_service', new HashService());
$container->set('chisodien_repository', new ChiSoDienRepository($database, $container->get('hash_service')));
$container->set('blockchain_repository', new BlockchainBatchRepository($database));
$container->set('merkle_service', new MerkleTreeService());
$container->set('blockchain_service', new BlockchainService(
    $container->get('blockchain_repository'),
    $container->get('chisodien_repository'),
    $container->get('hash_service'),
    $container->get('merkle_service')
));
$container->set('verification_service', new VerificationService(
    $container->get('blockchain_repository'),
    $container->get('chisodien_repository'),
    $container->get('hash_service'),
    $container->get('merkle_service')
));
$container->set('import_service', new ImportService(
    $container->get('chisodien_repository'),
    $container->get('database')
));
$container->set('import_controller', new ImportController(
    $container->get('import_service')
));
$container->set('blockchain_controller', new BlockchainController(
    $container->get('blockchain_service'),
    $container->get('verification_service')
));
$container->set('dashboard_controller', new DashboardController(
    $container->get('chisodien_repository'),
    $container->get('blockchain_repository')
));

return $container;
