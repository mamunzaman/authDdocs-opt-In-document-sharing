jQuery(document).ready(function ($) {
  "use strict";

  // Handle request access button clicks
  $(document).on("click", ".authdocs-request-access-btn", function (e) {
    e.preventDefault();
    const documentId = $(this).data("document-id");
    showRequestAccessModal(documentId);
  });

  // Handle load more button clicks
  $(document).on("click", ".authdocs-load-more-btn", function (e) {
    e.preventDefault();
    const $btn = $(this);
    const currentLimit = parseInt($btn.data("current-limit"));
    const restriction = $btn.data("restriction");
    const $grid = $btn
      .closest(".authdocs-grid-load-more")
      .prev(".authdocs-grid");

    loadMoreDocuments($grid, currentLimit, restriction, $btn);
  });

  // Handle pagination button clicks
  $(document).on("click", ".authdocs-pagination-btn", function (e) {
    e.preventDefault();
    const $btn = $(this);
    const page = parseInt($btn.data("page"));
    const $container = $btn.closest(".authdocs-grid-container");

    if (page && $container.length) {
      loadPageDocuments($container, page);
    }
  });

  // Show request access modal
  function showRequestAccessModal(documentId) {
    const modalHtml = `
            <div class="authdocs-modal" id="authdocs-request-modal">
                <div class="authdocs-modal-content">
                    <div class="authdocs-modal-header">
                        <span class="authdocs-close">&times;</span>
                        <h2 class="authdocs-modal-title">${
                          authdocs_frontend.request_access_title ||
                          "Request Document Access"
                        }</h2>
                    </div>
                    <div class="authdocs-modal-body">
                        <form id="authdocs-request-form">
                            <div class="authdocs-form-group">
                                <label for="authdocs-name">${
                                  authdocs_frontend.name_label || "Full Name"
                                }</label>
                                <input type="text" id="authdocs-name" name="name" required>
                            </div>
                            <div class="authdocs-form-group">
                                <label for="authdocs-email">${
                                  authdocs_frontend.email_label ||
                                  "Email Address"
                                }</label>
                                <input type="email" id="authdocs-email" name="email" required>
                            </div>
                            <input type="hidden" name="document_id" value="${documentId}">
                            <input type="hidden" name="nonce" value="${
                              authdocs_frontend.nonce
                            }">
                        </form>
                    </div>
                    <div class="authdocs-modal-footer">
                        <button type="button" class="authdocs-btn authdocs-btn-secondary authdocs-close">${
                          authdocs_frontend.cancel_label || "Cancel"
                        }</button>
                        <button type="button" class="authdocs-btn authdocs-btn-primary" id="authdocs-submit-request">${
                          authdocs_frontend.submit_label || "Submit Request"
                        }</button>
                    </div>
                </div>
            </div>
        `;

    $("body").append(modalHtml);
    $("#authdocs-request-modal").fadeIn(300);

    // Focus on first input
    $("#authdocs-name").focus();

    // Handle close button and overlay clicks
    $("#authdocs-request-modal .authdocs-close, #authdocs-request-modal").on(
      "click",
      function (e) {
        if (e.target === this) {
          closeRequestModal();
        }
      }
    );

    // Handle form submission
    $("#authdocs-submit-request").on("click", function () {
      submitAccessRequest();
    });

    // Handle Enter key in form
    $("#authdocs-request-form").on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        submitAccessRequest();
      }
    });
  }

  // Close request modal
  function closeRequestModal() {
    $("#authdocs-request-modal").fadeOut(300, function () {
      $(this).remove();
    });
  }

  // Submit access request
  function submitAccessRequest() {
    const $form = $("#authdocs-request-form");
    const $submitBtn = $("#authdocs-submit-request");
    const formData = new FormData($form[0]);

    // Add action for AJAX
    formData.append("action", "authdocs_request_access");

    // Disable submit button and show loading
    $submitBtn
      .prop("disabled", true)
      .text(authdocs_frontend.submitting_label || "Submitting...");

    $.ajax({
      url: authdocs_frontend.ajax_url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.success) {
          showSuccessMessage(
            response.data.message || "Access request submitted successfully!"
          );
          closeRequestModal();
        } else {
          showErrorMessage(
            response.data.message ||
              "Failed to submit request. Please try again."
          );
        }
      },
      error: function (xhr, status, error) {
        showErrorMessage("An error occurred. Please try again.");
      },
      complete: function () {
        $submitBtn
          .prop("disabled", false)
          .text(authdocs_frontend.submit_label || "Submit Request");
      },
    });
  }

  // Load more documents
  function loadMoreDocuments($grid, currentLimit, restriction, $btn) {
    const $loadMoreContainer = $btn.closest(".authdocs-grid-load-more");
    const newLimit = currentLimit + 12;

    // Show loading state
    $btn
      .prop("disabled", true)
      .text(authdocs_frontend.loading_label || "Loading...");

    $.ajax({
      url: authdocs_frontend.ajax_url,
      type: "POST",
      data: {
        action: "authdocs_load_more_documents",
        limit: newLimit,
        restriction: restriction,
        nonce: authdocs_frontend.nonce,
      },
      success: function (response) {
        if (response.success) {
          // Update grid content
          $grid.html(response.data.html);

          // Update button data and text
          $btn.data("current-limit", newLimit);

          // Hide load more button if no more documents
          if (response.data.has_more === false) {
            $loadMoreContainer.hide();
          }
        } else {
          showErrorMessage(
            response.data.message || "Failed to load more documents."
          );
        }
      },
      error: function () {
        showErrorMessage("An error occurred while loading documents.");
      },
      complete: function () {
        $btn
          .prop("disabled", false)
          .text(authdocs_frontend.load_more_label || "Load More Documents");
      },
    });
  }

  // Load page documents with pagination
  function loadPageDocuments($container, page) {
    const $grid = $container.find(".authdocs-grid");
    const $pagination = $container.find(".authdocs-pagination");
    const $paginationBtns = $container.find(".authdocs-pagination-btn");

    // Show loading state
    $paginationBtns.prop("disabled", true);
    $grid.addClass("loading");

    // Get container data attributes
    const limit = parseInt($container.data("limit")) || 12;
    const restriction = $container.data("restriction") || "all";
    const orderby = $container.data("orderby") || "date";
    const order = $container.data("order") || "DESC";

    $.ajax({
      url: authdocs_frontend.ajax_url,
      type: "POST",
      data: {
        action: "authdocs_paginate_documents",
        page: page,
        limit: limit,
        restriction: restriction,
        orderby: orderby,
        order: order,
        nonce: authdocs_frontend.nonce,
      },
      success: function (response) {
        if (response.success) {
          // Update grid content
          $grid.html(response.data.html);

          // Update pagination
          if (response.data.pagination_html) {
            $pagination.html(response.data.pagination_html);
          }

          // Update container data attributes
          $container.data("current-page", response.data.current_page);
          $container.data("total-pages", response.data.total_pages);
          $container.data("total-documents", response.data.total_documents);

          // Scroll to top of grid
          $("html, body").animate(
            {
              scrollTop: $container.offset().top - 100,
            },
            500
          );
        } else {
          showErrorMessage(
            response.data.message || "Failed to load documents."
          );
        }
      },
      error: function () {
        showErrorMessage("An error occurred while loading documents.");
      },
      complete: function () {
        $paginationBtns.prop("disabled", false);
        $grid.removeClass("loading");
      },
    });
  }

  // Show success message
  function showSuccessMessage(message) {
    const messageHtml = `
            <div class="authdocs-message authdocs-message-success">
                <span>${message}</span>
                <button type="button" class="authdocs-message-close">&times;</button>
            </div>
        `;

    $("body").append(messageHtml);

    // Auto-hide after 5 seconds
    setTimeout(function () {
      $(".authdocs-message-success").fadeOut(300, function () {
        $(this).remove();
      });
    }, 5000);

    // Handle close button
    $(".authdocs-message-close").on("click", function () {
      $(this)
        .closest(".authdocs-message")
        .fadeOut(300, function () {
          $(this).remove();
        });
    });
  }

  // Show error message
  function showErrorMessage(message) {
    const messageHtml = `
            <div class="authdocs-message authdocs-message-error">
                <span>${message}</span>
                <button type="button" class="authdocs-message-close">&times;</button>
            </div>
        `;

    $("body").append(messageHtml);

    // Auto-hide after 8 seconds
    setTimeout(function () {
      $(".authdocs-message-error").fadeOut(300, function () {
        $(this).remove();
      });
    }, 8000);

    // Handle close button
    $(".authdocs-message-close").on("click", function () {
      $(this)
        .closest(".authdocs-message")
        .fadeOut(300, function () {
          $(this).remove();
        });
    });
  }

  // Add message styles dynamically
  if (!$("#authdocs-message-styles").length) {
    const messageStyles = `
            <style id="authdocs-message-styles">
                .authdocs-message {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 4px;
                    color: #fff;
                    font-weight: 500;
                    z-index: 100001;
                    max-width: 400px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    animation: slideInRight 0.3s ease;
                }
                
                .authdocs-message-success {
                    background: #00a32a;
                }
                
                .authdocs-message-error {
                    background: #d63638;
                }
                
                .authdocs-message-close {
                    background: none;
                    border: none;
                    color: #fff;
                    font-size: 18px;
                    cursor: pointer;
                    margin-left: 10px;
                    opacity: 0.8;
                }
                
                .authdocs-message-close:hover {
                    opacity: 1;
                }
                
                @keyframes slideInRight {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
            </style>
        `;
    $("head").append(messageStyles);
  }
});
