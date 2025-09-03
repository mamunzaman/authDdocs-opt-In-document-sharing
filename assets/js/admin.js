jQuery(document).ready(function ($) {
  "use strict";

  // Handle request management actions
  $(".authdocs-action-link").on("click", function () {
    var $btn = $(this);
    var action = $btn.data("action");
    var requestId = $btn.data("request-id");
    var $row = $btn.closest("tr");

    var confirmMessage = "Are you sure you want to perform this action?";
    if (action === "decline") {
      confirmMessage =
        "Are you sure you want to revoke access? This will immediately invalidate any existing download links.";
    }

    // Show confirmation popup instead of browser confirm
    showConfirmDialog(confirmMessage, function () {
      // User confirmed, proceed with action
      proceedWithAction($btn, action, requestId);
    });
    return;
  });

  // WordPress native notice function
  function showNotice(message, type) {
    var noticeClass = type === "success" ? "notice-success" : "notice-error";
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

    // Auto-dismiss after 5 seconds
    setTimeout(function () {
      $notice.fadeOut();
    }, 5000);

    // Make dismissible
    $notice.on("click", ".notice-dismiss", function () {
      $notice.fadeOut();
    });
  }

  // WordPress native confirmation popup
  function showConfirmDialog(message, onConfirm) {
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

    // Trigger reflow and add show class for animation
    $overlay[0].offsetHeight;
    $overlay.addClass("show");

    // Handle button clicks
    $(".authdocs-confirm-ok").on("click", function () {
      $overlay.removeClass("show");
      setTimeout(function () {
        $overlay.remove();
        onConfirm();
      }, 300);
    });

    $(".authdocs-confirm-cancel").on("click", function () {
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

  // Function to proceed with the action after confirmation
  function proceedWithAction($btn, action, requestId) {
    // Show loading state
    $btn.addClass("loading").prop("disabled", true);

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "authdocs_manage_request",
        request_id: requestId,
        action_type: action,
        nonce: authdocs_admin.nonce,
      },
      success: function (response) {
        if (response.success) {
          var $row = $btn.closest("tr");
          var $table = $row.closest("table");

          // Determine action result message
          var actionMessage = "";
          var shouldRemoveRow = false;

          switch (action) {
            case "accept":
              actionMessage =
                "Request accepted successfully. Access granted email sent.";
              shouldRemoveRow = true;
              break;
            case "reaccept":
              actionMessage =
                "Request re-accepted successfully. Access granted email sent.";
              shouldRemoveRow = true;
              break;
            case "decline":
              actionMessage = "Request declined successfully. Access revoked.";
              shouldRemoveRow = true;
              break;
            case "inactive":
              actionMessage = "Request marked as inactive successfully.";
              shouldRemoveRow = false; // Keep row but update status
              break;
            default:
              actionMessage = "Request " + action + " successfully.";
              shouldRemoveRow = true;
          }

          // Show success message
          showNotice(actionMessage, "success");

          if (shouldRemoveRow) {
            // Show completion message
            showNotice(actionMessage, "success");

            // Add completed class for simple highlight
            $row.addClass("completed");

            // Remove highlight after 5 seconds with fade effect
            setTimeout(function () {
              $row.addClass("fade-out");
              setTimeout(function () {
                $row.removeClass("completed fade-out");
              }, 300);
            }, 5000);

            // Remove loading class and re-enable button
            $btn.removeClass("loading").prop("disabled", false);
          } else {
            // For inactive status, just update the button state
            $btn.removeClass("loading").prop("disabled", false);

            // Update the status column to show "Inactive"
            var $statusCell = $row.find(".column-status");
            if ($statusCell.length) {
              $statusCell.html(
                '<span class="authdocs-status-inactive">Inactive</span>'
              );
            }
          }
        } else {
          showNotice(
            "Error: " + (response.data || "Unknown error occurred"),
            "error"
          );
          $btn.removeClass("loading").prop("disabled", false);
        }
      },
      error: function () {
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
