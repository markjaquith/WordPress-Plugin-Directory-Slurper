WordPress Plugin Directory Slurper
==================================

A command line PHP script that downloads and updates a copy of the latest stable
version of every plugin in the [WordPress.org plugin repository][repo].

Really handy for doing local searches across all WordPress plugins.

[repo]: http://wordpress.org/extend/plugins/

Requirements
------------

* PHP 5.2
* wget
* Unix system (only tested on Mac OS X)

Instructions
------------

1. `cd WordPress-Plugin-Directory-Slurper`
2. `./update`

The `plugins/` directory will contain all the plugins, when the script is done.

FAQ
----

### Why download the zip files? Why not use SVN? ###

An SVN checkout of the entire repository is a BEAST of a thing. You don't want it, 
trust me. Updates and cleanups can take **hours** or even **days** to complete.

### Why not just do an SVN export of each plugin's trunk? ###

There is no guarantee that the plugin's trunk is the latest stable version. The 
repository supports doing development in trunk, and designating a branch or tag 
as the stable version. Using the zip file gets around this, as it figures it all 
out and gives you the latest stable version

### How long will it take? ###

Your first update will take a while. Perhaps a few hours. But subsequent updates 
are smart. The script tracks the SVN revision number of your latest update and 
then asks the Plugins Trac install for a list of plugins that have changed since. 
Only those changed plugins are updated after the initial sync.

### Something went wrong, how do I do a partial update? ###

The last successful update revision number is stored in `plugins/.last-revision`. 
You can just overwrite that and the next `update` will start after that revision.

Copyright & License
-------------------
Copyright (C) 2011 Mark Jaquith

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.