(() => {
    const PARENT = "elemacy";
    const EVT = "elemacy:locationchange";

    const routeFromHash = () => {
        const h = window.location.hash || "#/";
        return h.replace(/^#\/?/, "");
    };

    const fragmentFromHref = (href) => {
        if (!href) return null;
        let decoded = href;
        try { decoded = decodeURIComponent(href); } catch (e) {}
        const idx = decoded.indexOf("#");
        return idx === -1 ? null : decoded.slice(idx + 1);
    };

    const sync = () => {
        const menu = document.getElementById("adminmenu");
        if (!menu) return;
        const top = menu.querySelector("li.toplevel_page_" + PARENT);
        if (!top) return;
        const submenu = top.querySelector(".wp-submenu");
        if (!submenu) return;

        const current = routeFromHash();
        const links = submenu.querySelectorAll("a");
        let matched = null;

        links.forEach((a) => {
            const frag = fragmentFromHref(a.getAttribute("href"));
            if (frag === null) return;
            if (frag.replace(/^\//, "") === current) matched = a;
        });

        if (!matched && current === "") {
            for (const a of links) {
                if (fragmentFromHref(a.getAttribute("href")) === "") {
                    matched = a;
                    break;
                }
            }
        }

        links.forEach((a) => {
            const li = a.parentElement;
            const on = a === matched;
            a.classList.toggle("current", on);
            if (li && li.tagName === "LI") li.classList.toggle("current", on);
        });
    };

    ["pushState", "replaceState"].forEach((name) => {
        const orig = history[name];
        if (typeof orig !== "function" || orig.__elemacyPatched) return;
        const wrapped = function () {
            const ret = orig.apply(this, arguments);
            window.dispatchEvent(new Event(EVT));
            return ret;
        };
        wrapped.__elemacyPatched = true;
        history[name] = wrapped;
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", sync);
    } else {
        sync();
    }
    window.addEventListener("hashchange", sync);
    window.addEventListener("popstate", sync);
    window.addEventListener(EVT, sync);
})();
