jQuery(document).ready(function ($) {
  "use strict";

  console.log("AuthDocs: Admin JavaScript loaded successfully");
  console.log(
    "AuthDocs: Available actions:",
    $(".authdocs-action-link").length
  );
  console.log("AuthDocs: AJAX URL available:", typeof ajaxurl !== "undefined");
  console.log(
    "AuthDocs: Admin object available:",
    typeof authdocs_admin !== "undefined"
  );
  if (typeof authdocs_admin !== "undefined") {
    console.log("AuthDocs: Admin object:", authdocs_admin);
  }

  // Handle copy link button clicks
  $(document).on("click", ".authdocs-copy-link", function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $btn = $(this);
    var linkToCopy = $btn.data("link");

    // If data-link is not available, try to find the download link in the same row
    if (!linkToCopy) {
      var $downloadLink = $btn.closest("tr").find(".authdocs-download-link");
      if ($downloadLink.length > 0) {
        linkToCopy = $downloadLink.attr("href");
        console.log("AuthDocs: Found download link from href:", linkToCopy);
      }
    }

    console.log("AuthDocs: Copy button clicked");
    console.log("AuthDocs: Button element:", $btn);
    console.log("AuthDocs: Link to copy:", linkToCopy);

    if (!linkToCopy) {
      console.error("AuthDocs: No link data found for copy button");
      return;
    }

    // Use the Clipboard API if available
    if (navigator.clipboard && window.isSecureContext) {
      console.log("AuthDocs: Using Clipboard API to copy:", linkToCopy);
      navigator.clipboard
        .writeText(linkToCopy)
        .then(function () {
          console.log("AuthDocs: Clipboard API copy successful");
          showCopySuccess($btn);
        })
        .catch(function (err) {
          console.error("AuthDocs: Clipboard API failed, using fallback:", err);
          fallbackCopyTextToClipboard(linkToCopy, $btn);
        });
    } else {
      console.log("AuthDocs: Clipboard API not available, using fallback");
      // Fallback for older browsers or non-secure contexts
      fallbackCopyTextToClipboard(linkToCopy, $btn);
    }
  });

  // Fallback copy function for older browsers
  function fallbackCopyTextToClipboard(text, $btn) {
    console.log("AuthDocs: Using fallback copy method for text:", text);

    var textArea = document.createElement("textarea");
    textArea.value = text;

    // Avoid scrolling to bottom
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";

    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      var successful = document.execCommand("copy");
      console.log("AuthDocs: Fallback copy result:", successful);
      if (successful) {
        showCopySuccess($btn);
      } else {
        showCopyError($btn);
      }
    } catch (err) {
      console.error("AuthDocs: Fallback copy failed:", err);
      showCopyError($btn);
    }

    document.body.removeChild(textArea);
  }

  // Show copy success feedback
  function showCopySuccess($btn) {
    var $icon = $btn.find(".dashicons");
    var originalClass = $icon.attr("class");

    // Change icon to checkmark
    $icon.removeClass("dashicons-admin-page").addClass("dashicons-yes-alt");
    $btn.addClass("copied");

    // Show tooltip
    $btn.attr("title", "Link copied!");

    // Reset after 2 seconds
    setTimeout(function () {
      $icon.attr("class", originalClass);
      $btn.removeClass("copied");
      $btn.attr("title", "Copy link");
    }, 2000);
  }

  // Show copy error feedback
  function showCopyError($btn) {
    var $icon = $btn.find(".dashicons");
    var originalClass = $icon.attr("class");

    // Change icon to error
    $icon.removeClass("dashicons-admin-page").addClass("dashicons-warning");
    $btn.addClass("copy-error");

    // Show tooltip
    $btn.attr("title", "Copy failed");

    // Reset after 3 seconds
    setTimeout(function () {
      $icon.attr("class", originalClass);
      $btn.removeClass("copy-error");
      $btn.attr("title", "Copy link");
    }, 3000);
  }

  // Table sorting, filtering, and pagination functionality
  var currentSort = { column: null, direction: "asc" };
  var allRows = [];
  var filteredRows = [];
  var currentPage = 1;
  var rowsPerPage = 20;

  // Initialize table functionality
  function initTableFeatures() {
    // Store all rows for sorting/filtering
    allRows = $(".authdocs-requests-table-wrapper tbody tr").toArray();
    filteredRows = allRows.slice();

    // Initialize sortable headers
    $(".authdocs-sortable").on("click", function () {
      var column = $(this).data("sort");
      var direction = "asc";

      if (currentSort.column === column && currentSort.direction === "asc") {
        direction = "desc";
      }

      currentSort = { column: column, direction: direction };
      sortTable(column, direction);
      updateSortIcons();
    });

    // Initialize filter
    $("#authdocs-requests-filter").on("input", function () {
      var filterText = $(this).val().toLowerCase();
      filterTable(filterText);
    });
  }

  // Sort table by column
  function sortTable(column, direction) {
    filteredRows.sort(function (a, b) {
      var aVal = $(a).data(column);
      var bVal = $(b).data(column);

      // Handle different data types
      if (column === "date") {
        aVal = new Date(aVal);
        bVal = new Date(bVal);
      } else if (typeof aVal === "string") {
        aVal = aVal.toLowerCase();
        bVal = bVal.toLowerCase();
      }

      if (direction === "asc") {
        return aVal > bVal ? 1 : -1;
      } else {
        return aVal < bVal ? 1 : -1;
      }
    });

    renderTable();
  }

  // Filter table by text
  function filterTable(filterText) {
    if (filterText.length < 3) {
      filteredRows = allRows.slice();
    } else {
      filteredRows = allRows.filter(function (row) {
        var searchData = $(row).data("search");
        return searchData.indexOf(filterText) !== -1;
      });
    }

    currentPage = 1;
    renderTable();
  }

  // Render table with current sort/filter
  function renderTable() {
    var tbody = $(".authdocs-requests-table-wrapper tbody");
    tbody.empty();

    var startIndex = (currentPage - 1) * rowsPerPage;
    var endIndex = startIndex + rowsPerPage;
    var pageRows = filteredRows.slice(startIndex, endIndex);

    tbody.append(pageRows);
    updatePaginationInfo();
  }

  // Update sort icons
  function updateSortIcons() {
    $(".authdocs-sortable").removeClass("sorted sorted-desc");
    if (currentSort.column) {
      var $header = $(
        '.authdocs-sortable[data-sort="' + currentSort.column + '"]'
      );
      $header.addClass(
        currentSort.direction === "desc" ? "sorted-desc" : "sorted"
      );
    }
  }

  // Update pagination info
  function updatePaginationInfo() {
    var total = filteredRows.length;
    var start = total === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
    var end = Math.min(currentPage * rowsPerPage, total);

    $("#authdocs-pagination-info").text(
      "Showing " + start + "-" + end + " of " + total + " requests"
    );
  }

  // Initialize on document ready
  $(document).ready(function () {
    initTableFeatures();
  });

  // Handle request management actions
  $(document).on("click", ".authdocs-action-link", function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $btn = $(this);

    console.log("AuthDocs: Action link clicked", $btn);
    console.log("AuthDocs: Button classes:", $btn.attr("class"));
    console.log("AuthDocs: Button data attributes:", $btn.data());

    // Prevent action if button is disabled
    if ($btn.hasClass("disabled") || $btn.prop("disabled")) {
      console.log("AuthDocs: Button is disabled, preventing action");
      return false;
    }

    var action = $btn.data("action");
    var requestId = $btn.data("request-id");
    var $row = $btn.closest("tr");

    console.log("AuthDocs: Action:", action, "Request ID:", requestId);

    var confirmMessage = "Are you sure you want to perform this action?";
    if (action === "decline") {
      confirmMessage =
        "Are you sure you want to revoke access? This will immediately invalidate any existing download links.";
    } else if (action === "inactive") {
      // Check current status to determine the action
      var $currentRow = $btn.closest("tr");
      var currentStatus = $currentRow
        .find("td[data-label='Status'] .authdocs-status")
        .text()
        .toLowerCase();

      if (currentStatus === "inactive") {
        confirmMessage =
          "Are you sure you want to activate this request? This will enable Accept/Decline options.";
      } else {
        confirmMessage =
          "Are you sure you want to deactivate this request? This will disable access and prevent further actions.";
      }
    }

    // Show confirmation popup instead of browser confirm
    console.log(
      "AuthDocs: Showing confirmation dialog with message:",
      confirmMessage
    );
    console.log("AuthDocs: About to call showConfirmDialog");
    showConfirmDialog(confirmMessage, function () {
      // User confirmed, proceed with action
      console.log(
        "AuthDocs: User confirmed action, proceeding with:",
        action,
        requestId
      );
      proceedWithAction($btn, action, requestId);
    });
    console.log("AuthDocs: showConfirmDialog called, returning");
    return;
  });

  // WordPress native notice function
  function showNotice(message, type) {
    var noticeClass = "notice-info"; // Default to info

    if (type === "success") {
      noticeClass = "notice-success";
    } else if (type === "error") {
      noticeClass = "notice-error";
    } else if (type === "warning") {
      noticeClass = "notice-warning";
    }

    var $notice = $(
      '<div class="notice ' +
        noticeClass +
        ' is-dismissible"><p>' +
        message +
        "</p></div>"
    );

    // Remove any existing notices
    $(".notice").remove();

    // Add notice to the top of the page
    $(".wrap h1").after($notice);

    // Notifications are now persistent - they stay until manually dismissed or page reload

    // Make dismissible
    $notice.on("click", ".notice-dismiss", function () {
      $notice.fadeOut();
    });
  }

  // WordPress native confirmation popup
  function showConfirmDialog(message, onConfirm) {
    console.log(
      "AuthDocs: Creating confirmation dialog with message:",
      message
    );

    // Remove any existing confirmation dialogs
    $(".authdocs-confirm-overlay").remove();

    var $overlay = $('<div class="authdocs-confirm-overlay"></div>');
    var $dialog = $(
      '<div class="authdocs-confirm-dialog">' +
        '<div class="authdocs-confirm-header">' +
        "<h3>Confirm Action</h3>" +
        "</div>" +
        '<div class="authdocs-confirm-body">' +
        "<p>" +
        message +
        "</p>" +
        "</div>" +
        '<div class="authdocs-confirm-footer">' +
        '<button type="button" class="button button-secondary authdocs-confirm-cancel">Cancel</button>' +
        '<button type="button" class="button button-primary authdocs-confirm-ok">OK</button>' +
        "</div>" +
        "</div>"
    );

    $overlay.append($dialog);
    $("body").append($overlay);

    console.log("AuthDocs: Confirmation dialog created and appended to body");
    console.log("AuthDocs: Overlay element:", $overlay);
    console.log("AuthDocs: Dialog element:", $dialog);

    // Trigger reflow and add show class for animation
    $overlay[0].offsetHeight;
    $overlay.addClass("show");
    console.log("AuthDocs: Show class added to overlay");

    // Handle button clicks using event delegation
    $overlay.on("click", ".authdocs-confirm-ok", function () {
      console.log("AuthDocs: Confirmation dialog OK clicked");
      $overlay.removeClass("show");
      setTimeout(function () {
        $overlay.remove();
        onConfirm();
      }, 300);
    });

    $overlay.on("click", ".authdocs-confirm-cancel", function () {
      console.log("AuthDocs: Confirmation dialog Cancel clicked");
      $overlay.removeClass("show");
      setTimeout(function () {
        $overlay.remove();
      }, 300);
    });

    // Close on overlay click
    $overlay.on("click", function (e) {
      if (e.target === this) {
        $overlay.removeClass("show");
        setTimeout(function () {
          $overlay.remove();
        }, 300);
      }
    });

    // Close on Escape key
    $(document).on("keydown.authdocs-confirm", function (e) {
      if (e.key === "Escape") {
        $overlay.removeClass("show");
        setTimeout(function () {
          $overlay.remove();
        }, 300);
        $(document).off("keydown.authdocs-confirm");
      }
    });
  }

  // Function to refresh row data after status change
  function refreshRowData($row, requestId) {
    console.log("refreshRowData called for request ID:", requestId);
    // Get the current row data and update it
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "authdocs_get_request_data",
        request_id: requestId,
        nonce: authdocs_admin.nonce,
      },
      success: function (response) {
        console.log("AJAX response received:", response);
        if (response.success && response.data) {
          var request = response.data;
          console.log("Request data:", request);

          // Update status column with modern design
          var $statusCell = $row.find("td[data-label='Status']");
          if ($statusCell.length) {
            var statusIcons = {
              pending: "dashicons-clock",
              accepted: "dashicons-yes-alt",
              declined: "dashicons-no-alt",
              inactive: "dashicons-hidden",
            };
            var statusColors = {
              pending: "#ffc107",
              accepted: "#28a745",
              declined: "#dc3545",
              inactive: "#6c757d",
            };
            var icon = statusIcons[request.status] || "dashicons-info";
            var color = statusColors[request.status] || "#6c757d";

            $statusCell.html(
              '<div class="authdocs-status-modern authdocs-status-' +
                request.status +
                '">' +
                '<span class="authdocs-status-icon" style="color: ' +
                color +
                '">' +
                '<span class="dashicons ' +
                icon +
                '"></span>' +
                "</span>" +
                '<span class="authdocs-status-text">' +
                request.status.charAt(0).toUpperCase() +
                request.status.slice(1) +
                "</span>" +
                "</div>"
            );
            console.log("Updated status cell with modern design");
          }

          // Update file link column
          var $fileLinkCell = $row.find("td[data-label='File Link']");
          if ($fileLinkCell.length && request.document_file) {
            if (request.status === "accepted" && request.secure_hash) {
              var downloadUrl =
                authdocs_admin.site_url +
                "?authdocs_download=" +
                request.document_id +
                "&hash=" +
                request.secure_hash +
                "&email=" +
                encodeURIComponent(request.requester_email) +
                "&request_id=" +
                request.id;
              $fileLinkCell.html(
                '<div class="authdocs-link-container">' +
                  '<a href="' +
                  downloadUrl +
                  '" target="_blank" class="authdocs-download-link" title="Click to view document">' +
                  "View Document" +
                  "</a>" +
                  '<button type="button" class="authdocs-copy-link" title="Copy link" data-link="' +
                  downloadUrl +
                  '">' +
                  '<span class="dashicons dashicons-admin-page"></span>' +
                  "</button>" +
                  "</div>"
              );
              console.log("Updated file link cell with download link");
            } else {
              // Show appropriate status message based on request status
              var statusMessage = "";
              var linkClass = "authdocs-file-link";

              switch (request.status) {
                case "declined":
                  statusMessage = "Access declined";
                  linkClass = "authdocs-file-link authdocs-declined";
                  break;
                case "inactive":
                  statusMessage = "Request deactivated";
                  linkClass = "authdocs-file-link authdocs-inactive";
                  break;
                case "pending":
                  statusMessage = "Request pending approval";
                  break;
                case "accepted":
                  // This should not happen here as accepted status is handled above
                  statusMessage = "Access granted";
                  break;
                default:
                  statusMessage = "Request not accepted yet";
              }

              $fileLinkCell.html(
                '<div class="authdocs-link-container">' +
                  '<span class="dashicons dashicons-lock authdocs-link-lock"></span>' +
                  '<a href="' +
                  request.document_file.url +
                  '" target="_blank" class="' +
                  linkClass +
                  '">' +
                  "View File" +
                  "</a>" +
                  "</div><br>" +
                  '<small class="authdocs-status-note ' +
                  request.status +
                  '">' +
                  statusMessage +
                  "</small>"
              );
              console.log(
                "Updated file link cell with regular link - status:",
                request.status
              );
            }
          }

          // Update action buttons with proper disabled states
          var $acceptBtn = $row.find(".authdocs-action-accept");
          var $declineBtn = $row.find(".authdocs-action-decline");
          var $inactiveBtn = $row.find(".authdocs-action-inactive");
          var $deleteBtn = $row.find(".authdocs-action-delete");

          // Determine button states based on current status
          var acceptDisabled =
            request.status === "accepted" || request.status === "inactive";
          var declineDisabled =
            request.status === "declined" || request.status === "inactive";
          // Toggle button should always be enabled to allow switching between states
          var toggleDisabled = false;

          // Update Accept button
          if ($acceptBtn.length) {
            var acceptText;
            if (request.status === "accepted") {
              acceptText = "Already accepted";
            } else if (request.status === "inactive") {
              acceptText = "Link is hidden - Show link first";
            } else {
              acceptText = "Accept";
            }
            $acceptBtn.attr("title", acceptText);

            if (acceptDisabled) {
              $acceptBtn.addClass("disabled").prop("disabled", true);
            } else {
              $acceptBtn.removeClass("disabled").prop("disabled", false);
            }
            console.log("Updated accept button - disabled:", acceptDisabled);
          }

          // Update Decline button
          if ($declineBtn.length) {
            var declineText;
            if (request.status === "declined") {
              declineText = "Already declined";
            } else if (request.status === "inactive") {
              declineText = "Link is hidden - Show link first";
            } else {
              declineText = "Decline";
            }
            $declineBtn.attr("title", declineText);

            if (declineDisabled) {
              $declineBtn.addClass("disabled").prop("disabled", true);
            } else {
              $declineBtn.removeClass("disabled").prop("disabled", false);
            }
            console.log("Updated decline button - disabled:", declineDisabled);
          }

          // Update Toggle Link Visibility button
          if ($inactiveBtn.length) {
            var toggleText =
              request.status === "inactive"
                ? "Link is hidden - Click to show"
                : "Link is visible - Click to hide";
            $inactiveBtn.attr("title", toggleText);

            // Update icon based on current state
            var $icon = $inactiveBtn.find(".dashicons");
            if (request.status === "inactive") {
              $icon
                .removeClass("dashicons-hidden")
                .addClass("dashicons-visibility");
            } else {
              $icon
                .removeClass("dashicons-visibility")
                .addClass("dashicons-hidden");
            }

            // Toggle button should always be enabled
            $inactiveBtn.removeClass("disabled").prop("disabled", false);

            console.log(
              "Updated toggle link visibility button - status:",
              request.status
            );
          }

          // Update Delete button (always enabled)
          if ($deleteBtn.length) {
            $deleteBtn.attr("title", "Delete request");
            $deleteBtn.removeClass("disabled").prop("disabled", false);
            console.log("Updated delete button");
          }

          // Remove loading class and re-enable button
          $row
            .find(".authdocs-action-link")
            .removeClass("loading")
            .prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX error:", status, error);
        console.error("Response text:", xhr.responseText);
        // If refresh fails, just remove loading state
        $row
          .find(".authdocs-action-link")
          .removeClass("loading")
          .prop("disabled", false);
      },
    });
  }

  // Function to proceed with the action after confirmation
  function proceedWithAction($btn, action, requestId) {
    // Show loading state
    $btn.addClass("loading").prop("disabled", true);

    console.log("AuthDocs: Making AJAX request with data:", {
      action: "authdocs_manage_request",
      request_id: requestId,
      action_type: action,
      nonce: authdocs_admin.nonce,
    });
    var ajaxUrl =
      typeof ajaxurl !== "undefined"
        ? ajaxurl
        : typeof authdocs_admin !== "undefined"
        ? authdocs_admin.ajax_url
        : "/wp-admin/admin-ajax.php";
    var nonce =
      typeof authdocs_admin !== "undefined" ? authdocs_admin.nonce : "";
    console.log("AuthDocs: AJAX URL:", ajaxUrl);
    console.log("AuthDocs: Nonce value:", nonce);

    if (!nonce) {
      console.error(
        "AuthDocs: No nonce available, cannot proceed with AJAX request"
      );
      showNotice("Security error: No nonce available.", "error");
      $btn.removeClass("loading").prop("disabled", false);
      return;
    }

    $.ajax({
      url: ajaxUrl,
      type: "POST",
      data: {
        action: "authdocs_manage_request",
        request_id: requestId,
        action_type: action,
        nonce: nonce,
      },
      success: function (response) {
        console.log("Main AJAX response:", response);
        console.log("AuthDocs: AJAX success - response type:", typeof response);
        console.log(
          "AuthDocs: AJAX success - response.success:",
          response.success
        );
        if (response.success) {
          var $row = $btn.closest("tr");
          var $table = $row.closest("table");
          console.log("Row found:", $row.length > 0);

          // Get message from server response
          var actionMessage =
            response.data.message || "Request updated successfully.";
          var emailSent = response.data.email_sent || false;
          var newStatus = response.data.status || "";

          console.log("Action message:", actionMessage);
          console.log("Email sent:", emailSent);
          console.log("New status:", newStatus);

          // Determine if we should remove the row (for accept/decline actions)
          var shouldRemoveRow = false;
          if (action === "accept" || action === "decline") {
            shouldRemoveRow = true;
          }

          // Show success message with email status
          var messageType = emailSent ? "success" : "warning";
          if (
            !emailSent &&
            (newStatus === "accepted" || newStatus === "declined")
          ) {
            messageType = "warning";
          }

          showNotice(actionMessage, messageType);

          // Handle delete action - remove row completely
          if (action === "delete" && response.data && response.data.deleted) {
            $row.fadeOut(300, function () {
              $(this).remove();
            });
            return;
          }

          if (shouldRemoveRow) {
            // Add highlighting class for visual feedback
            $row.addClass("authdocs-row-updated");
            console.log(
              "AuthDocs: Added row highlighting for request ID:",
              requestId
            );

            // Remove highlight after 5 seconds with fade effect
            setTimeout(function () {
              $row.addClass("fade-out");
              console.log(
                "AuthDocs: Starting fade-out for request ID:",
                requestId
              );

              // Remove classes after fade transition completes
              setTimeout(function () {
                $row.removeClass("authdocs-row-updated fade-out");
                console.log(
                  "AuthDocs: Removed highlighting classes for request ID:",
                  requestId
                );
              }, 300); // Match CSS transition duration
            }, 5000);

            // Refresh the row data to show updated status and file link
            console.log(
              "About to call refreshRowData for request ID:",
              requestId
            );
            refreshRowData($row, requestId);
          } else {
            // For inactive status, add highlighting and refresh the row data
            $row.addClass("authdocs-row-updated");
            console.log(
              "AuthDocs: Added row highlighting for inactive status, request ID:",
              requestId
            );

            // Remove highlight after 5 seconds with fade effect
            setTimeout(function () {
              $row.addClass("fade-out");
              console.log(
                "AuthDocs: Starting fade-out for inactive status, request ID:",
                requestId
              );

              // Remove classes after fade transition completes
              setTimeout(function () {
                $row.removeClass("authdocs-row-updated fade-out");
                console.log(
                  "AuthDocs: Removed highlighting classes for inactive status, request ID:",
                  requestId
                );
              }, 300); // Match CSS transition duration
            }, 5000);

            refreshRowData($row, requestId);
          }
        } else {
          showNotice(
            "Error: " + (response.data || "Unknown error occurred"),
            "error"
          );
          $btn.removeClass("loading").prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        console.error("AuthDocs: AJAX error:", status, error);
        console.error("AuthDocs: Response text:", xhr.responseText);
        console.error("AuthDocs: XHR status:", xhr.status);
        console.error("AuthDocs: XHR readyState:", xhr.readyState);
        showNotice("Network error. Please try again.", "error");
        $btn.removeClass("loading").prop("disabled", false);
      },
    });
  }

  // Handle bulk actions (if needed in future)
  $("#bulk-action-selector-top, #bulk-action-selector-bottom").on(
    "change",
    function () {
      var action = $(this).val();
      if (action && action !== "-1") {
        $("#doaction, #doaction2").prop("disabled", false);
      } else {
        $("#doaction, #doaction2").prop("disabled", true);
      }
    }
  );

  // Auto-refresh functionality removed - was causing page reloads every 30 seconds
  // If auto-refresh is needed in the future, implement it with AJAX instead of page reload

  // Handle document file upload in admin
  if ($("#authdocs_upload_button").length) {
    var file_frame;

    $("#authdocs_upload_button").on("click", function (e) {
      e.preventDefault();

      if (file_frame) {
        file_frame.open();
        return;
      }

      file_frame = wp.media.frames.file_frame = wp.media({
        title: "Select Document",
        button: {
          text: "Use this document",
        },
        multiple: false,
        library: {
          type: [
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
          ],
        },
      });

      file_frame.on("select", function () {
        var attachment = file_frame.state().get("selection").first().toJSON();
        $("#authdocs_file_id").val(attachment.id);
        $("#authdocs_file_preview").html(
          '<p><a href="' +
            attachment.url +
            '" target="_blank">' +
            attachment.filename +
            "</a></p>"
        );
      });

      file_frame.open();
    });
  }

  // Handle shortcode copy functionality
  $("input[readonly]").on("click", function () {
    $(this).select();
  });

  // Add tooltips for better UX
  $(".authdocs-action-btn").each(function () {
    var $btn = $(this);
    var action = $btn.data("action");
    var tooltip = "";

    switch (action) {
      case "accept":
        tooltip = "Approve this request and generate download link";
        break;
      case "decline":
        tooltip = "Reject this request";
        break;
      case "inactive":
        tooltip = "Deactivate this request";
        break;
    }

    if (tooltip) {
      $btn.attr("title", tooltip);
    }
  });

  // Handle responsive table improvements
  function handleResponsiveTable() {
    if ($(window).width() < 782) {
      $(".authdocs-requests-table-wrapper .wp-list-table").addClass(
        "mobile-view"
      );
    } else {
      $(".authdocs-requests-table-wrapper .wp-list-table").removeClass(
        "mobile-view"
      );
    }
  }

  $(window).on("resize", handleResponsiveTable);
  handleResponsiveTable();
});
