<?php

namespace App;

use App\Lsp\CodeActionProvider;
use App\Lsp\Commands\CreateComponentCommand;
use App\Lsp\Commands\CreateLivewireComponentCommand;
use App\Lsp\Handlers\BladeComponentHandler;
use App\Lsp\Handlers\BladeValidatorHandler;
use App\Lsp\Handlers\RefreshOnFileChangeHandle;
use Phpactor\LanguageServer\Adapter\Psr\AggregateEventDispatcher;
use Phpactor\LanguageServer\Core\CodeAction\AggregateCodeActionProvider;
use Phpactor\LanguageServer\Core\Command\CommandDispatcher;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsEngine;
use Phpactor\LanguageServer\Core\Dispatcher\ArgumentResolver\PassThroughArgumentResolver;
use Phpactor\LanguageServer\Core\Dispatcher\ArgumentResolver\LanguageSeverProtocolParamsResolver;
use Phpactor\LanguageServer\Core\Dispatcher\ArgumentResolver\ChainArgumentResolver;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServer\Listener\WorkspaceListener;
use Phpactor\LanguageServer\Middleware\CancellationMiddleware;
use Phpactor\LanguageServer\Middleware\ErrorHandlingMiddleware;
use Phpactor\LanguageServer\Middleware\InitializeMiddleware;
use Phpactor\LanguageServerProtocol\InitializeParams;
use Phpactor\LanguageServer\Core\Dispatcher\Dispatcher;
use Phpactor\LanguageServer\Core\Handler\HandlerMethodRunner;
use Phpactor\LanguageServer\Core\Dispatcher\DispatcherFactory;
use Phpactor\LanguageServer\Middleware\ResponseHandlingMiddleware;
use Phpactor\LanguageServer\Core\Handler\Handlers;
use Phpactor\LanguageServer\Core\Dispatcher\Dispatcher\MiddlewareDispatcher;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\ResponseWatcher\DeferredResponseWatcher;
use Phpactor\LanguageServer\Core\Server\RpcClient\JsonRpcClient;
use Phpactor\LanguageServer\Core\Server\Transmitter\MessageTransmitter;
use Phpactor\LanguageServer\Core\Service\ServiceManager;
use Phpactor\LanguageServer\Core\Service\ServiceProviders;
use Phpactor\LanguageServer\Handler\System\ExitHandler;
use Phpactor\LanguageServer\Handler\System\ServiceHandler;
use Phpactor\LanguageServer\Handler\TextDocument\CodeActionHandler;
use Phpactor\LanguageServer\Handler\TextDocument\TextDocumentHandler;
use Phpactor\LanguageServer\Handler\Workspace\CommandHandler;
use Phpactor\LanguageServer\Handler\Workspace\DidChangeWatchedFilesHandler;
use Phpactor\LanguageServer\Listener\DidChangeWatchedFilesListener;
use Phpactor\LanguageServer\Listener\ServiceListener;
use Phpactor\LanguageServer\Middleware\HandlerMiddleware;
use Phpactor\LanguageServer\Service\DiagnosticsService;
use Psr\Log\LoggerInterface;

class BladeDispatcherFactory implements DispatcherFactory
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function create(MessageTransmitter $transmitter, InitializeParams $initializeParams): Dispatcher
    {
        $responseWatcher = new DeferredResponseWatcher();

        $clientApi = new ClientApi(new JsonRpcClient($transmitter, $responseWatcher));

        $store = new DataStore();
        $store->refreshAvailableComponents();

        $workspace = new Workspace($this->logger);

        $diagnosticsService = new DiagnosticsService(
            $diagnosticsEngine = new DiagnosticsEngine($clientApi, new BladeValidatorHandler($store)),
            lintOnUpdate: true,
            lintOnSave: true,
            workspace: $workspace
        );

        $serviceProviders = new ServiceProviders($diagnosticsService);

        $serviceManager = new ServiceManager($serviceProviders, $this->logger);

        $commandHandler = new CommandHandler(
            new CommandDispatcher([
                'create_component' => new CreateComponentCommand(
                    dataStore: $store,
                    diagnosticsEngine: $diagnosticsEngine,
                    api: $clientApi
                ),
                'create_livewire_component' => new CreateLivewireComponentCommand(
                    dataStore: $store,
                    diagnosticsEngine: $diagnosticsEngine,
                    api: $clientApi
                )
            ])
        );

        $codeActionHandler = new CodeActionHandler(
            new AggregateCodeActionProvider(
                new CodeActionProvider($store)
            ),
            $workspace
        );

        $eventDispatcher = new AggregateEventDispatcher(
            new ServiceListener($serviceManager),
            new WorkspaceListener($workspace),
            new DidChangeWatchedFilesListener(
                $clientApi,
                ['resources/views/**/*.blade.php', 'app/Http/Livewire/**/*.php', 'app/View/**/*.php'],
                $initializeParams->capabilities
            ),
            $diagnosticsService,
        );

        $handlers = new Handlers(
            new TextDocumentHandler($eventDispatcher),
            new ServiceHandler($serviceManager, $clientApi),
            new DidChangeWatchedFilesHandler($eventDispatcher),
            new BladeComponentHandler($this->logger, $workspace, $store),
            new RefreshOnFileChangeHandle($store),
            $commandHandler,
            $codeActionHandler,
            new ExitHandler()
        );

        $runner = new HandlerMethodRunner(
            $handlers,
            new ChainArgumentResolver(
                new LanguageSeverProtocolParamsResolver(),
                new PassThroughArgumentResolver()
            ),
        );

        return new MiddlewareDispatcher(
            new ErrorHandlingMiddleware($this->logger),
            new InitializeMiddleware($handlers, $eventDispatcher, [
                'version' => 1,
            ]),
            new CancellationMiddleware($runner),
            new ResponseHandlingMiddleware($responseWatcher),
            new HandlerMiddleware($runner)
        );
    }
}
