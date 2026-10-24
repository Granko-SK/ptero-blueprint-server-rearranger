# Server Order (Blueprint extension for Pterodactyl)

Set your own order for the servers on the admin **Servers** page by dragging them. The dashboard uses the same order.

## What it does

- **Admin → Servers:** a six-dot handle sits beside each wrench icon. Press and hold it, then move the row up or down. The order saves when you let go.
- **Dashboard:** the server list is sorted to match. Servers that aren't in the saved order (for example, new ones) appear after the ordered servers, in their usual order.
- **Admin → Extensions → Server order:** the extension's own page. It shows how many servers are in the saved order and has a **Reset order** button. The button is not on the Servers list.

## Install

Requires a Pterodactyl panel with Blueprint installed. Put `serverorder.blueprint` in your panel folder, then run:

```bash
cd /var/www/pterodactyl
sudo blueprint -install serverorder
```

If Blueprint warns that the extension's target version doesn't match your panel, the install still works.

## Remove

Click **Reset order** first (on Admin → Extensions → Server order). Removing the extension doesn't delete the saved order from the database. Then run:

```bash
cd /var/www/pterodactyl
sudo blueprint -remove serverorder
```

## Limits

- Dragging works on one page of the admin Servers list at a time. You can't move a server to another page.
- The admin part reads the layout of the Servers page. A Pterodactyl update that changes that layout may break the handle until the extension is updated.
- The order is the same for every user on the dashboard. Each user only sees the servers they can access.
- Tested on one panel with the Darkenate theme. Not yet tested on a clean panel.

## Compatibility

Built for Blueprint target `beta-2025-11`.

## License

MIT. See [LICENSE](LICENSE).
