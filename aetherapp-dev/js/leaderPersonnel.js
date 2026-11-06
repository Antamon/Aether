"use strict";

// Keep existing company bookmarks working with the shared company page.
window.addEventListener("load", () => {
    window.location.replace(`companies.html${window.location.search}`);
});
