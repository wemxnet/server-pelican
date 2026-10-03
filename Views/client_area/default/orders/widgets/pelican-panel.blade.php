@if(isset($order) && \Extensions\Servers\Pelican\Server::usesPelican($order) && !empty($order->package->serverConnection->config['hostname']))
    <x-theme::card class="mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Game Server</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage your server, files, and console on the game panel.
                    @if($account = $order->getExternalUser())
                        <br>Username: <span class="font-medium">{{ $account->username }}</span>
                    @endif
                </p>
            </div>
            <x-theme::button.primary :href="$order->package->serverConnection->config['hostname']" target="_blank" text="Login to Game Panel" />
        </div>
    </x-theme::card>
@endif
