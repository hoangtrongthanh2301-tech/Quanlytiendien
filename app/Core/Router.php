<?php

namespace App\Core;

class Router
{
    private Request $request;
    private Container $container;

    public function __construct(Request $request, Container $container)
    {
        $this->request = $request;
        $this->container = $container;
    }

    public function dispatch(): void
    {
        $action = $this->request->input('action', 'status');

        switch ($action) {
            case 'import':
                $controller = $this->container->get('import_controller');
                $controller->import($this->request);
                break;
            case 'create_batch':
                $controller = $this->container->get('blockchain_controller');
                $controller->createBatch($this->request);
                break;
            case 'verify_batch':
                $controller = $this->container->get('blockchain_controller');
                $controller->verifyBatch($this->request);
                break;
            case 'verify_chain':
                $controller = $this->container->get('blockchain_controller');
                $controller->verifyChain();
                break;
            case 'dashboard':
                $controller = $this->container->get('dashboard_controller');
                $controller->summary($this->request);
                break;
            case 'status':
            default:
                Response::json(['success' => true, 'message' => 'Blockchain / Meter Management API is available.']);
                break;
        }
    }
}
