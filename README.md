# Pelican

Provision game servers on a [Pelican](https://pelican.dev) panel from WemX. Each order creates a panel user when needed, then a server on a node in the chosen location that has room and a free allocation.

## Features

- Application API connection with a panel hostname and API key
- Locations that group one or more Pelican nodes
- Package egg, with the egg's variables, Docker image, and startup command loaded after the egg is saved
- CPU, memory, disk, swap, block IO, and CPU pinning limits
- Database, allocation, and backup limits
- Configurable `MAX_PLAYERS` and `SERVER_NAME` variables
- Check for a viable node before the package is added to the cart
- Create a panel user for the customer and email the login
- "Login to Game Panel" button on the order page
- Suspend, unsuspend, terminate, and password changes

## Install

Install from the WemX marketplace, or download `Pelican.zip` from a GitHub release and place the `Pelican` folder at `extensions/Servers/Pelican`. Then enable **Pelican Server**.

Publishing a release builds `Pelican.zip`. Unzipping it creates a folder named `Pelican`. GitHub’s own “Source code” archive still unpacks to `server-pelican-<tag>`. Use `Pelican.zip`.

## Connection

Set the panel URL without a trailing slash, for example `https://panel.example.com`. The API key is an application API key and must start with `peli_`.

## Locations

Locations are defined in `extensions/Servers/Pelican/Config/pelican.php`. Each location has a display name and the ids of the nodes that belong to it:

```php
'locations' => [
    'germany' => [
        'name' => 'Germany',
        'nodes' => [1, 2],
    ],
],
```

To keep your locations across extension updates, copy that file to `config/server-pelican.php` in the root of your WemX installation and edit it there.

## Packages

Pick the location and enter the egg ID, then save the package once so WemX can load that egg's variables, Docker image, and startup command. Mark location, CPU, memory, disk, limits, `MAX_PLAYERS`, and `SERVER_NAME` as configurable options when customers should choose them at checkout.

Memory and disk are entered in GB and sent to the panel in MB. Swap is entered in MB. `0` means unlimited for CPU, memory, and disk.
