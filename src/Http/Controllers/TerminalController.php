<?php

namespace Recca0120\Terminal\Http\Controllers;

use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Recca0120\Terminal\Kernel;

class TerminalController extends Controller
{
    /**
     * index.
     *
     * @param  \Recca0120\Terminal\Kernel  $kernel
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Routing\ResponseFactory  $responseFactory
     * @param  string  $view
     * @return \Illuminate\Http\Response
     *
     * @throws \Exception
     */
    public function index(Kernel $kernel, Request $request, ResponseFactory $responseFactory, $view = 'index')
    {
        // Laravel 12 compatible session token retrieval
        $token = '';
        try {
            if ($request->hasSession() && $request->session()) {
                $token = $request->session()->token();
            } elseif (function_exists('csrf_token')) {
                $token = csrf_token();
            }
        } catch (Exception $e) {
            // Fallback: generate a token manually
            $token = Str::random(40);
        }

        try {
            $kernel->call('list --ansi');
            $helpInfo = $kernel->output();
        } catch (Exception $e) {
            $helpInfo = 'Terminal initialization failed: ' . $e->getMessage();
        }

        $options = json_encode(array_merge($kernel->getConfig(), [
            'csrfToken' => $token,
            'helpInfo' => $helpInfo
        ]), JSON_UNESCAPED_SLASHES);

        $id = ($view === 'panel') ? Str::random(30) : null;

        return $responseFactory->view('terminal::' . $view, compact('options', 'id'));
    }

    /**
     * rpc response.
     *
     * @param  \Recca0120\Terminal\Kernel  $kernel
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Routing\ResponseFactory  $responseFactory
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function endpoint(Kernel $kernel, Request $request, ResponseFactory $responseFactory)
    {
        try {
            $method = $request->get('method', '');
            $params = $request->get('params', []);

            // Validate the method to prevent arbitrary command execution
            if (empty($method) || !is_string($method)) {
                throw new Exception('Invalid method');
            }

            // Additional security: validate method against allowed patterns
            if (!$this->isValidCommand($method)) {
                throw new Exception('Command not allowed');
            }

            $code = $kernel->call($method, $params);

            $attributes = $code === 0
                ? [
                    'jsonrpc' => $request->get('jsonrpc'),
                    'id' => $request->get('id'),
                    'result' => $kernel->output()
                ]
                : [
                    'jsonrpc' => $request->get('jsonrpc'),
                    'id' => null,
                    'error' => [
                        'code' => -32600,
                        'message' => 'Invalid Request',
                        'data' => $kernel->output()
                    ]
                ];
        } catch (Exception $e) {
            $attributes = [
                'jsonrpc' => $request->get('jsonrpc'),
                'id' => null,
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal Error',
                    'data' => $e->getMessage()
                ]
            ];
        }

        return $responseFactory->json($attributes);
    }

    /**
     * media.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Routing\ResponseFactory  $responseFactory
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  string  $file
     * @return \Illuminate\Http\Response
     */
    public function media(Request $request, ResponseFactory $responseFactory, Filesystem $files, $file)
    {
        $filename = __DIR__ . '/../../../public/' . $file;

        // Security check to prevent directory traversal
        $realPath = realpath($filename);
        $allowedPath = realpath(__DIR__ . '/../../../public/');

        if (!$realPath || !$allowedPath || strpos($realPath, $allowedPath) !== 0) {
            return $responseFactory->make('File not found', 404);
        }

        if (!$files->exists($filename)) {
            return $responseFactory->make('File not found', 404);
        }

        $mimeType = strpos($filename, '.css') !== false ? 'text/css' : 'application/javascript';
        $lastModified = $files->lastModified($filename);
        $eTag = sha1_file($filename);

        $headers = [
            'content-type' => $mimeType,
            'last-modified' => date('D, d M Y H:i:s ', $lastModified) . 'GMT',
        ];

        if (
            @strtotime($request->server('HTTP_IF_MODIFIED_SINCE')) === $lastModified ||
            trim($request->server('HTTP_IF_NONE_MATCH'), '"') === $eTag
        ) {
            $response = $responseFactory->make(null, 304, $headers);
        } else {
            $response = $responseFactory->stream(function () use ($filename, $files) {
                $out = fopen('php://output', 'wb');
                $file = fopen($filename, 'rb');
                stream_copy_to_stream($file, $out, $files->size($filename));
                fclose($out);
                fclose($file);
            }, 200, $headers);
        }

        return $response->setEtag($eTag);
    }

    /**
     * Validate if the command is allowed
     *
     * @param string $command
     * @return bool
     */
    private function isValidCommand($command)
    {
        // Allow common Laravel artisan commands
        $allowedPatterns = [
            '/^list/',
            '/^help/',
            '/^route:list/',
            '/^cache:clear/',
            '/^config:clear/',
            '/^view:clear/',
            '/^migrate:status/',
            '/^queue:work/',
            '/^queue:restart/',
            '/^storage:link/',
            '/^optimize/',
            // Add more patterns as needed
        ];

        foreach ($allowedPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }

        // Block dangerous commands
        $blockedPatterns = [
            '/^migrate/',  // Block migrations (potentially dangerous)
            '/^db:seed/',  // Block seeding
            '/^key:generate/', // Block key generation
            '/^down/',     // Block maintenance mode
            '/^up/',       // Block maintenance mode
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return false;
            }
        }

        return true; // Allow other commands
    }
}
