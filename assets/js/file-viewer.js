/**
 * File Viewer JavaScript
 * Handles Office viewer loading states and interactions
 */

(function () {
  "use strict";

  // Wait for DOM to be ready
  document.addEventListener("DOMContentLoaded", function () {
    // Handle PDF viewer loading states
    const pdfIframe = document.getElementById("pdf-viewer-iframe");
    const pdfFallback = document.getElementById("pdf-fallback");

    if (pdfIframe && pdfFallback) {
      // Show iframe when it loads successfully
      pdfIframe.addEventListener("load", () => {
        pdfIframe.style.display = "block";
        pdfFallback.style.display = "none";
      });

      // Show fallback on error
      pdfIframe.addEventListener("error", () => {
        pdfIframe.style.display = "none";
        pdfFallback.style.display = "flex";
      });

      // Fallback timeout - if PDF doesn't load within 10 seconds, show fallback
      setTimeout(() => {
        if (pdfIframe.style.display !== "block") {
          pdfIframe.style.display = "none";
          pdfFallback.style.display = "flex";
        }
      }, 10000);
    }

    // Handle Office viewer loading states
    const iframes = document.querySelectorAll(".office-viewer");
    const loadingElements = document.querySelectorAll(".office-viewer-loading");
    const fallbackElements = document.querySelectorAll(
      ".office-viewer-fallback"
    );

    iframes.forEach((iframe, index) => {
      const loading = loadingElements[index];
      const fallback = fallbackElements[index];

      if (loading && fallback) {
        // Hide loading after 10 seconds and show fallback
        setTimeout(() => {
          if (loading.style.display !== "none") {
            loading.style.display = "none";
            fallback.style.display = "flex";
          }
        }, 10000);

        // Hide loading when iframe loads
        iframe.addEventListener("load", () => {
          loading.style.display = "none";
        });

        // Show fallback on error
        iframe.addEventListener("error", () => {
          loading.style.display = "none";
          fallback.style.display = "flex";
        });
      }
    });
  });
})();
