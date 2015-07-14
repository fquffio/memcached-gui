# Memcached-GUI
Simple web GUI for memcached. With this basic tool you can view, add, edit, remove or massively flush every item stored in memcached. You can also filter items by key for a quicker access.

In large systems, pagination will prevent your page from becoming huge. Large values are cropped for the same reason, too, and you can view them in their integrity by clicking on "View Raw" button.

![Screenshot](http://i.imgur.com/Ku3Plb4.png "Memcached-GUI view")

## Requirements
The `memcached` extension of PHP is required for this to work (please note the trailing "d", as PHP has two different extensions for memcached), as well as a running memcached server.

## Installation

- Clone the repository.
- Rename `config.ini.sample` to `config.ini` and edit it if necessary.
- Enjoy!

### Quick install

Unless you have an exotically-configured memcached server, or the server you wish to monitor isn't running on local host, this is enough to get this stuff working:

```
git clone git@github.com:fquffio/memcached-gui.git && cp memcached-gui/config.ini.sample memcached-gui/config.ini
```
