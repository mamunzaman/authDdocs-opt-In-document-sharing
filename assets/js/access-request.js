/**
 * Access Request Form Handler
 * Handles form submission for document access requests
 */

(function () {
  "use strict";

  // Wait for DOM to be ready
  document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("authdocs-request-form");
    if (!form) return;

    const submitBtn = document.getElementById("authdocs-submit-request");
    if (!submitBtn) return;

    // Add form submission handling
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      const formData = new FormData(this);

      // Disable button and show loading
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<span style="display: inline-block; width: 16px; height: 16px; border: 2px solid transparent; border-top: 2px solid currentColor; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px;"></span>Submitting...';

      // Get AJAX URL from data attribute or use default
      const ajaxUrl =
        form.dataset.ajaxUrl ||
        window.location.origin + "/wp-admin/admin-ajax.php";

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            alert(
              data.data.message || "Access request submitted successfully!"
            );
            window.location.href =
              form.dataset.redirectUrl || window.location.origin;
          } else {
            alert(
              data.data.message || "Failed to submit request. Please try again."
            );
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("An error occurred. Please try again.");
        })
        .finally(() => {
          // Re-enable button
          submitBtn.disabled = false;
          submitBtn.innerHTML =
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" fill="currentColor"/></svg>Request Access';
        });
    });

    // Add spin animation CSS
    const style = document.createElement("style");
    style.textContent =
      "@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }";
    document.head.appendChild(style);
  });
})();
