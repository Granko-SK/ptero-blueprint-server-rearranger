import { useEffect } from 'react';
import http from '@/api/http';

// Puts the dashboard's server list in the order saved on the admin Servers page.
// Servers that are not in the saved order keep their usual place, after the ordered ones.

const ORDER_ENDPOINT = '/api/client/extensions/serverorder/order';

// Gap between rows. Matches Tailwind's mt-2 that the dashboard uses.
const ROW_GAP = '0.5rem';

const serverIdFromHref = (href: string | null): string | null => {
    const match = href?.match(/\/server\/([^/?#]+)/);
    return match ? match[1] : null;
};

export default () => {
    useEffect(() => {
        let order: string[] = [];
        let loaded = false;

        const apply = () => {
            if (!loaded) return;

            const links = Array.from(document.querySelectorAll<HTMLAnchorElement>('a[href^="/server/"]'));
            if (links.length < 2) return;

            const parent = links[0].parentElement;
            if (!parent) return;

            // The server rows that share a parent are the list we sort.
            const rows = links.filter((link) => link.parentElement === parent);

            const rank = (link: HTMLAnchorElement) => {
                const index = order.indexOf(serverIdFromHref(link.getAttribute('href')) ?? '');
                return index === -1 ? Number.MAX_SAFE_INTEGER : index;
            };
            const sorted = rows.slice().sort((a, b) => rank(a) - rank(b));

            // Only move rows when the order is actually wrong. This also stops the observer from looping on its own moves.
            const inOrder = rows.every((row, index) => row === sorted[index]);
            if (!inOrder) {
                const after = rows[rows.length - 1].nextSibling;
                sorted.forEach((row) => parent.insertBefore(row, after));
            }

            // The dashboard gives a row a top margin based on its position when it was rendered,
            // so after a move some rows would sit with no gap. Set the margins by the new order.
            sorted.forEach((row, index) => {
                const margin = index === 0 ? '0px' : ROW_GAP;
                if (row.style.marginTop !== margin) row.style.marginTop = margin;
            });
        };

        // React re-renders the list, so sort again whenever the page changes.
        const observer = new MutationObserver(() => apply());
        observer.observe(document.body, { childList: true, subtree: true });

        http.get(ORDER_ENDPOINT)
            .then((response) => {
                const data = response.data as { order?: unknown };
                order = Array.isArray(data.order)
                    ? data.order.filter((id): id is string => typeof id === 'string')
                    : [];
                loaded = true;
                apply();
            })
            .catch(() => undefined);

        return () => observer.disconnect();
    }, []);

    return null;
};
