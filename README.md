# A simple blueprint pterodactyl extension which allows you to rearrange the order of your servers in your pterodactyl panel 

You can edit the server order from the admin panel under the Servers category and drag the servers to change the order. A simple thing that pterodactyl doesn't support out of the box for some reason.

# Installation
**Automatic insatllation: `cd /var/www/pterodactyl && curl -fsSL -o serverorder.blueprint https://github.com/Granko-SK/ptero-blueprint-server-rearranger/releases/download/release/serverorder.blueprint && sudo blueprint -install serverorder`**
**To install, move the `serverorder.blueprint` file to `/var/www/pterodactyl` (or wherever you have your pterodactyl files), `cd` into that folder and run the blueprint install command: `sudo blueprint -install serverorder`**

# Updating the extension
**Updating is the same process as installing. Move the new `serverorder.blueprint` file to `/var/www/pterodactyl` (or wherever you have your pterodactyl files), `cd` into that folder and run the blueprint install command: `sudo blueprint -install serverorder`**

# Removing the extension
**To remove, first open the extension's settings and click Reset order so the server order file gets removed. Then, `cd` into the folder you installed the blueprint from (likely `/var/www/pterodactyl`). then, run `sudo blueprint -remove serverorder`**

# Compatibility

Built for Blueprint target beta-2025-11. Tested on beta-2026-08. Tested on pterodactyl versions 1.15.1 and 1.15.2.

# Limitations
**- Layout changes in pterodactyl might break the extension until i update it**

**- I only tested it on my own already set up panel. I also tried it with the Darkenate theme installed (so it will probably work on other themes too). I HAVEN'T TESTED IT ON A CLEAN PANEL!**

**- The order is the same for every user on the dashboard. Each user only sees the servers they can access.**


*by the way, this extension is fully made by AI. the extension is tested by me, but there may or may not be bugs (but its really just an extension to rearrange servers so there is not much that can break there..)*


License

MIT. See LICENSE.
