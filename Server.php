<?php

namespace Extensions\Servers\Pelican;

use App\Extensions\Foundation\ServerExtension;
use App\Models\Order;
use App\Models\Package;
use App\Models\ServerConnection;
use Exception;
use Extensions\Servers\Pelican\Providers\EmailServiceProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Server extends ServerExtension
{
    protected string $id = 'server-pelican';

    protected string $name = 'Pelican Server';

    protected string $description = 'Provision game servers on a Pelican panel.';

    protected string $type = 'Server';

    protected string $version = '1.0.0';

    protected array $wemxVersions = ['*'];

    protected array $authors = [
        [
            'name' => 'WemX',
            'email' => 'mubeen@wemx.net',
        ],
    ];

    /**
     * Relative path to the extension config file. Locations and their nodes live here.
     */
    protected string $config = 'Config/pelican.php';

    public function providers(): array
    {
        return [
            EmailServiceProvider::class,
        ];
    }

    public function elements(): array
    {
        return [
            [
                'element' => 'client-order-top-view',
                'view' => 'server-pelican::client_area.default.orders.widgets.pelican-panel',
            ],
        ];
    }

    public function setConfig(): array
    {
        $doesNotEndWithSlash = function ($attribute, $value, $fail) {
            if (is_string($value) && preg_match('/\/$/', $value)) {
                $fail('Hostname URL must not end with a slash "/". It should be like https://panel.example.com');
            }
        };

        return [
            [
                'key' => 'hostname',
                'name' => 'Hostname',
                'description' => 'Hostname of your Pelican panel i.e https://panel.example.com',
                'type' => 'url',
                'default_value' => 'https://panel.example.com',
                'rules' => ['required', 'active_url', $doesNotEndWithSlash],
            ],
            [
                'key' => 'api_key',
                'name' => 'API Key',
                'description' => 'Application API key of your Pelican panel',
                'type' => 'password',
                'rules' => ['required', 'starts_with:peli_'],
            ],
        ];
    }

    public function setPackageConfig(Package $package, ServerConnection $connection): array
    {
        $locations = collect(self::locations())
            ->mapWithKeys(fn ($location, $key) => [$key => $location['name'] ?? $key])
            ->toArray();

        $config = [
            [
                'key' => 'location_id',
                'name' => 'Location',
                'col' => 'col-12',
                'description' => 'The location on which the server should be deployed. Locations and their nodes are defined in Config/pelican.php of this extension. Make this option configurable to let customers pick a location.',
                'type' => 'select',
                'options' => $locations,
                'default_value' => array_key_first($locations),
                'rules' => ['required', 'string', 'in:'.implode(',', array_keys($locations))],
                'is_configurable' => true,
            ],
            [
                'key' => 'egg_id',
                'name' => 'Egg ID',
                'col' => 'col-12',
                'description' => 'Egg ID of the server you want to use for this package. You can find the egg ID by going to the egg page and looking at the URL. It will be the number at the end of the URL. Save the package once to load the egg variables.',
                'type' => 'text',
                'rules' => ['required', 'numeric'],
                'is_configurable' => false,
            ],
        ];

        if (! $package->data('egg_id')) {
            return $config;
        }

        try {
            $eggId = (int) $package->data('egg_id');
            $connectionId = $connection->id ?? 'default';

            $egg = Cache::remember("pelican:egg:{$connectionId}:{$eggId}", now()->addHour(), function () use ($connection, $eggId) {
                $response = self::makeRequest($connection->config, "/api/application/eggs/{$eggId}", 'get', ['include' => 'variables']);

                if (! isset($response['attributes'])) {
                    throw new \RuntimeException('Invalid egg response from panel.');
                }

                return $response['attributes'];
            });
        } catch (\Throwable) {
            return $config;
        }

        $config = array_merge($config, [
            [
                'col' => 'col-4',
                'key' => 'database_limit',
                'name' => 'Database Limit',
                'description' => 'The total number of databases a user is allowed to create for this server on Pelican Panel.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['required', 'numeric', 'min:0'],
                'is_configurable' => true,
            ],
            [
                'col' => 'col-4',
                'key' => 'allocation_limit',
                'name' => 'Allocation Limit',
                'description' => 'The total number of allocations a user is allowed to create for this server on Pelican Panel.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['required', 'numeric', 'min:0'],
                'is_configurable' => true,
            ],
            [
                'col' => 'col-4',
                'key' => 'backup_limit',
                'name' => 'Backup Limit',
                'description' => 'The total number of backups a user is allowed to create for this server on Pelican Panel.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['required', 'numeric', 'min:0'],
                'is_configurable' => true,
            ],
            [
                'col' => 'col-4',
                'key' => 'cpu_limit',
                'name' => 'CPU Limit in %',
                'description' => 'If you do not want to limit CPU usage, set the value to 0. To use a single thread set it to 100%, for 4 threads set to 400% etc',
                'type' => 'number',
                'default_value' => 100,
                'min' => 0,
                'rules' => ['required', 'numeric', 'min:0'],
                'is_configurable' => true,
            ],
            [
                'col' => 'col-4',
                'key' => 'memory_limit',
                'name' => 'Memory Limit in GB',
                'description' => 'The maximum amount of memory allowed for this container. Setting this to 0 will allow unlimited memory in a container.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['required', 'numeric', 'min:0'],
                'is_configurable' => true,
            ],
            [
                'col' => 'col-4',
                'key' => 'disk_limit',
                'name' => 'Disk Limit in GB',
                'description' => 'The maximum amount of disk space allowed for this container. Setting this to 0 will allow unlimited disk space.',
                'type' => 'number',
                'min' => 0,
                'rules' => ['required', 'numeric', 'min:0'],
                'is_configurable' => true,
            ],
            [
                'col' => 'col-4',
                'key' => 'cpu_pinning',
                'name' => 'CPU Pinning (optional)',
                'description' => 'Advanced: Enter the specific CPU threads that this process can run on, or leave blank to allow all threads. This can be a single number, or a comma separated list. Example: 0, 0-1,3, or 0,1,3,4.',
                'type' => 'text',
                'rules' => ['nullable'],
                'is_configurable' => false,
            ],
            [
                'col' => 'col-4',
                'key' => 'swap_limit',
                'name' => 'Swap Limit in MB',
                'description' => 'The maximum amount of swap allowed for this container. Setting this to 0 will disable swap. Setting this to -1 will allow unlimited swap.',
                'type' => 'number',
                'default_value' => 0,
                'rules' => ['required', 'numeric', 'min:-1'],
                'is_configurable' => false,
            ],
            [
                'col' => 'col-4',
                'key' => 'block_io_weight',
                'name' => 'Block IO Weight',
                'description' => 'The relative weight of IO for this container. This accepts a value between 10 and 1000. The default value is 500.',
                'type' => 'number',
                'default_value' => 500,
                'rules' => ['required', 'numeric', 'min:10', 'max:1000'],
                'is_configurable' => false,
            ],
            [
                'col' => 'col-12',
                'key' => 'docker_image',
                'name' => 'Docker Image',
                'description' => 'Docker image to use for this server',
                'type' => 'text',
                'default_value' => self::defaultDockerImage($egg),
                'rules' => ['required'],
                'is_configurable' => false,
            ],
            [
                'col' => 'col-12',
                'key' => 'startup',
                'name' => 'Startup Command',
                'description' => 'Startup command for this server',
                'type' => 'textarea',
                'default_value' => data_get($egg, 'startup'),
                'rules' => ['required'],
                'is_configurable' => false,
            ],
        ]);

        foreach (data_get($egg, 'relationships.variables.data', []) as $variable) {
            $variable = $variable['attributes'] ?? [];

            if (! isset($variable['env_variable'])) {
                continue;
            }

            $rules = $variable['rules'] ?? 'nullable';

            $config[] = [
                'col' => 'col-4',
                'key' => "environment.{$variable['env_variable']}",
                'name' => $variable['name'] ?? $variable['env_variable'],
                'description' => $variable['description'] ?? '',
                'type' => 'text',
                'default_value' => $variable['default_value'] ?? '',
                'rules' => is_string($rules) ? explode('|', $rules) : $rules,
                'is_configurable' => false,
            ];
        }

        $config[] = [
            'col' => 'col-4',
            'key' => 'MAX_PLAYERS',
            'name' => 'Max Players Environment Variable',
            'description' => 'Maximum number of players allowed on the server. Sent to the server as the MAX_PLAYERS variable.',
            'type' => 'text',
            'default_value' => 20,
            'rules' => ['required'],
            'is_configurable' => true,
        ];

        $config[] = [
            'col' => 'col-4',
            'key' => 'SERVER_NAME',
            'name' => 'Server Name Environment Variable',
            'description' => 'Name of the server. Sent to the server as the SERVER_NAME variable.',
            'type' => 'text',
            'default_value' => $package->name,
            'rules' => ['required'],
            'is_configurable' => true,
        ];

        return $config;
    }

    /**
     * Called right before a package is added to the cart.
     * Ensures the chosen location has a node and a free allocation.
     *
     * @throws Exception
     */
    public static function eventAddToCart(Package $package, $configOptions = [])
    {
        $locationId = $configOptions['location_id'] ?? $package->data('location_id');

        if (! $locationId) {
            throw new Exception('Location has not been configured for this package');
        }

        if (! array_key_exists($locationId, self::locations())) {
            throw new Exception('Invalid location');
        }

        $allowedNodes = self::locations()[$locationId]['nodes'] ?? [];

        if (empty($allowedNodes)) {
            throw new Exception('No nodes are available for this location');
        }

        self::findViableNode(
            connection: $package->serverConnection,
            allowedNodes: $allowedNodes,
            diskLimit: self::gigabytesToMegabytes($configOptions['disk_limit'] ?? $package->data('disk_limit', 0)),
            memoryLimit: self::gigabytesToMegabytes($configOptions['memory_limit'] ?? $package->data('memory_limit', 0)),
            cpuLimit: $configOptions['cpu_limit'] ?? $package->data('cpu_limit', 0),
        );
    }

    public static function testConnection(array $credentials)
    {
        self::makeRequest($credentials, '/api/application/users');

        return 'Successfully connected to Pelican API';
    }

    /**
     * Make a request to the Pelican application API.
     */
    public static function makeRequest(array $credentials, string $endpoint, string $method = 'get', array $data = []): Response
    {
        $method = strtolower($method);

        if (! in_array($method, ['get', 'post', 'put', 'delete', 'patch'])) {
            throw new Exception('Invalid method');
        }

        $hostname = rtrim($credentials['hostname'] ?? '', '/');

        $response = Http::withToken($credentials['api_key'] ?? '')
            ->acceptJson()
            ->$method($hostname.$endpoint, $data);

        if ($response->failed()) {
            throw new Exception("Failed to connect to Pelican API at endpoint: $endpoint with status code: {$response->status()} and response: {$response->body()}");
        }

        return $response;
    }

    /**
     * Change the password of the Pelican user associated with the order.
     */
    public function changePassword(Order $order, string $newPassword)
    {
        $pelicanUser = $order->getExternalUser()->data;

        self::makeRequest($order->package->serverConnection->config, "/api/application/users/{$pelicanUser['id']}", 'patch', [
            'email' => $pelicanUser['email'],
            'username' => $pelicanUser['username'],
            'password' => $newPassword,
        ]);

        $order->updateExternalPassword($newPassword);
    }

    public function create(Order $order, ServerConnection $connection)
    {
        $pelicanUserId = $this->getOrCreatePelicanUser($order, $connection);
        $package = $order->package;

        $locationId = $order->option('location_id');
        $nodes = self::locations()[$locationId]['nodes'] ?? [];

        $diskLimit = self::gigabytesToMegabytes($order->option('disk_limit', 0));
        $memoryLimit = self::gigabytesToMegabytes($order->option('memory_limit', 0));
        $cpuLimit = $order->option('cpu_limit', 0);

        $node = self::findViableNode(
            connection: $connection,
            allowedNodes: $nodes,
            diskLimit: $diskLimit,
            memoryLimit: $memoryLimit,
            cpuLimit: $cpuLimit,
        );

        $environment = $package->data('environment', []);

        if ($order->option('MAX_PLAYERS')) {
            $environment['MAX_PLAYERS'] = $order->option('MAX_PLAYERS');
        }

        if ($order->option('SERVER_NAME')) {
            $environment['SERVER_NAME'] = $order->option('SERVER_NAME');
        }

        $limits = [
            'memory' => $memoryLimit,
            'swap' => (int) $order->option('swap_limit', 0),
            'disk' => $diskLimit,
            'io' => (int) $order->option('block_io_weight', 500),
            'cpu' => (int) $cpuLimit,
        ];

        if ($package->data('cpu_pinning')) {
            $limits['threads'] = $package->data('cpu_pinning');
        }

        $response = self::makeRequest($connection->config, '/api/application/servers', 'post', [
            'external_id' => "wemx{$order->id}",
            'name' => "{$package->name} [{$order->user->username}]",
            'user' => $pelicanUserId,
            'egg' => (int) $package->data('egg_id'),
            'startup' => $package->data('startup'),
            'docker_image' => $package->data('docker_image'),
            'environment' => $environment,
            'limits' => $limits,
            'feature_limits' => [
                'databases' => (int) $order->option('database_limit', 0),
                'allocations' => (int) $order->option('allocation_limit', 0),
                'backups' => (int) $order->option('backup_limit', 0),
            ],
            'allocation' => [
                'default' => $node['allocation_id'],
            ],
            'start_on_completion' => true,
            'skip_scripts' => false,
            'oom_disabled' => false,
            'swap_disabled' => false,
        ]);

        if (! isset($response['attributes'])) {
            throw new Exception('Failed to create server on Pelican panel');
        }

        $server = $response['attributes'];

        $order->update([
            'external_id' => $server['id'],
            'data' => $server,
        ]);
    }

    public function suspend(Order $order, ServerConnection $connection)
    {
        self::makeRequest($connection->config, "/api/application/servers/{$order->external_id}/suspend", 'post');
    }

    public function unsuspend(Order $order, ServerConnection $connection)
    {
        self::makeRequest($connection->config, "/api/application/servers/{$order->external_id}/unsuspend", 'post');
    }

    public function terminate(Order $order, ServerConnection $connection)
    {
        self::makeRequest($connection->config, "/api/application/servers/{$order->external_id}", 'delete');
    }

    /**
     * Locations from Config/pelican.php, keyed by location id.
     *
     * @return array<string, array{name?: string, nodes?: array<int, int>}>
     */
    public static function locations(): array
    {
        return config('server-pelican.locations', []);
    }

    public static function usesPelican(?Order $order): bool
    {
        return $order?->package?->serverConnection?->extension_identifier === 'server-pelican';
    }

    /**
     * Create the user on the Pelican panel and store the data locally.
     * If the user already exists, return the user id on the Pelican panel.
     */
    private function getOrCreatePelicanUser(Order $order, ServerConnection $connection): int
    {
        $user = $order->user;

        if ($order->hasExternalUser()) {
            return (int) $order->getExternalUser()->external_id;
        }

        try {
            $existing = self::makeRequest($connection->config, '/api/application/users', 'get', [
                'filter[email]' => $user->email,
            ]);

            if (isset($existing['data'][0]['attributes'])) {
                $this->storePelicanUserLocally($order, $existing['data'][0]['attributes']);

                return (int) $existing['data'][0]['attributes']['id'];
            }
        } catch (Exception) {
            // fall through and attempt to create the user
        }

        $password = Str::random(16);

        $created = self::makeRequest($connection->config, '/api/application/users', 'post', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'username' => $user->username.$user->id,
            'password' => $password,
        ]);

        $this->storePelicanUserLocally($order, array_merge($created['attributes'], ['password' => $password]));

        $this->emailPelicanCredentials($order, $connection, $user->email, $password);

        return (int) $created['attributes']['id'];
    }

    private function storePelicanUserLocally(Order $order, array $pelicanUser): void
    {
        $password = $pelicanUser['password'] ?? 'unknown';
        unset($pelicanUser['password']);

        $order->createExternalUser([
            'external_id' => $pelicanUser['id'],
            'username' => $pelicanUser['username'],
            'password' => $password,
            'data' => $pelicanUser,
        ]);
    }

    private function emailPelicanCredentials(Order $order, ServerConnection $connection, string $email, string $password): void
    {
        $order->user->email([
            'identifier' => 'server.pelican.account_created',
            'mailable_type' => Order::class,
            'mailable_id' => $order->id,
            'subject' => 'Game Panel Account Created',
            'lines' => [
                'Your account has been created on the game panel.',
                'You can login using the following details:',
                "Email: {$email}",
                "Password: {$password}",
            ],
            'variables' => [
                'panel_email' => $email,
                'panel_password' => $password,
            ],
            'button' => [
                'text' => 'Login to Game Panel',
                'url' => $connection->config['hostname'] ?? route('orders.view', $order->id),
            ],
        ]);
    }

    /**
     * Find a node with a free allocation inside the allowed nodes.
     *
     * @return array{node_id: int, allocation_id: int}
     */
    private static function findViableNode(ServerConnection $connection, array $allowedNodes = [], string|int|float $diskLimit = 0, string|int|float $memoryLimit = 0, string|int|float $cpuLimit = 0): array
    {
        $response = self::makeRequest($connection->config, '/api/application/nodes/deployable', 'get', [
            'disk' => $diskLimit,
            'memory' => $memoryLimit,
            'cpu' => $cpuLimit,
            'include' => 'allocations',
        ]);

        if (empty($response['data'])) {
            throw new Exception('Could not find node satisfying the requirements');
        }

        foreach ($response['data'] as $node) {
            $node = $node['attributes'];

            if (! empty($allowedNodes) && ! in_array($node['id'], $allowedNodes)) {
                continue;
            }

            foreach (data_get($node, 'relationships.allocations.data', []) as $allocation) {
                $allocation = $allocation['attributes'];

                if ($allocation['assigned']) {
                    continue;
                }

                return [
                    'node_id' => $node['id'],
                    'allocation_id' => $allocation['id'],
                ];
            }

            throw new Exception('Could not find a free allocation on the node, please contact support');
        }

        throw new Exception('Could not find a node satisfying the requirements');
    }

    private static function gigabytesToMegabytes(mixed $value): int
    {
        return (int) round(((float) $value) * 1024);
    }

    /**
     * Pelican eggs expose docker_images as a "label => image" map.
     */
    private static function defaultDockerImage(array $egg): ?string
    {
        if (! empty($egg['docker_image'])) {
            return $egg['docker_image'];
        }

        $images = $egg['docker_images'] ?? [];

        return is_array($images) && $images !== [] ? (string) reset($images) : null;
    }
}
