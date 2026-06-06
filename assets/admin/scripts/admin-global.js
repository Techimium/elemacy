(() => {
    const badges = document.querySelectorAll("#adminmenu .elemacy-external-menu-link");

    badges.forEach((badge) => {
        const link = badge.closest("a");
        if (!link) return;
        link.target = "_blank";
        link.rel = "noopener";
    });
})();
