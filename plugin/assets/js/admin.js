(function ($) {
  let pollTimer = null;

  function setBusy(isBusy) {
    $("#fontawesome-elementor-addon-kit-setup-start").prop("disabled", isBusy);
    $("#fontawesome-elementor-addon-kit-setup-spinner").toggleClass(
      "is-active",
      isBusy,
    );
  }

  // Normalize the many shapes an error payload can arrive in into a flat list
  // of message strings.
  // Handles arrays, single-object ({ message }), and string payloads.
  function normalizeErrors(payload) {
    if (Array.isArray(payload)) {
      return payload
        .map(function (entry) {
          if (typeof entry === "string") {
            return entry;
          }
          return entry && entry.message ? entry.message : null;
        })
        .filter(Boolean);
    }

    if (payload && typeof payload === "object" && payload.message) {
      return [payload.message];
    }

    if (typeof payload === "string" && payload) {
      return [payload];
    }

    return [];
  }

  function clearErrors() {
    $("#fontawesome-elementor-errors-subsection").hide().find("p").remove();
  }

  function displayErrors(errors) {
    const messages = normalizeErrors(errors);

    for (const message of messages) {
      $("#fontawesome-elementor-errors-subsection").append(
        $("<p>").text(message),
      );
    }

    if (messages.length > 0) {
      $("#fontawesome-elementor-errors-subsection").show();
    }
  }

  // Surface a failed AJAX request. Always logs the raw response to the console,
  // so even a bare 500 with no JSON body (e.g. a PHP fatal / WSOD) leaves a
  // diagnostic trail instead of vanishing.
  function handleAjaxFailure(context, jqXHR) {
    console.error(
      "[fontawesome-elementor-addon] " + context + " request failed",
      {
        status: jqXHR && jqXHR.status,
        statusText: jqXHR && jqXHR.statusText,
        responseText: jqXHR && jqXHR.responseText,
      },
    );

    const data = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data;
    const messages = normalizeErrors(data);

    if (messages.length > 0) {
      displayErrors(messages);
    } else {
      displayErrors(
        "The server returned an error (HTTP " +
          ((jqXHR && jqXHR.status) || "unknown") +
          ") without any details. Enable WP_DEBUG_LOG and check your server error log, plus the browser console, for more information.",
      );
    }
  }

  function poll(buildId) {
    const delayMs = 1000;

    // Clear any existing timer before starting a new polling loop
    if (pollTimer) {
      clearTimeout(pollTimer);
      pollTimer = null;
    }

    function doPoll() {
      $.post(FontawesomeElementorAddonAdmin.ajaxurl, {
        action: "fontawesome_elementor_addon_kit_setup_status",
        nonce: FontawesomeElementorAddonAdmin.nonce,
        build_id: buildId,
      })
        .done(function (resp) {
          if (!resp || !resp.success) {
            $("#fontawesome-elementor-addon-kit-setup-spinner").hide();
            $("#fontawesome-elementor-addon-kit-setup-status-progress").hide();
            $("#fontawesome-elementor-addon-kit-setup-status-fail").show();
            displayErrors((resp && resp.data) || "Error checking status.");
            setBusy(false);
            pollTimer = null;
            return;
          }

          const data = resp.data;
          $("#fontawesome-elementor-addon-kit-setup-status-progress").text(
            data.message || data.status,
          );

          if (data.done) {
            setBusy(false);
            $("#fontawesome-elementor-addon-kit-setup-spinner").hide();
            $("#fontawesome-elementor-addon-kit-setup-status-progress").hide();
            $("#fontawesome-elementor-addon-kit-setup-status-success").show();
            const lastKitRefreshAtFormatted =
              data?.last_kit_refresh_at_formatted;

            if (lastKitRefreshAtFormatted) {
              $("#fontawesome-elementor-addon-last-kit-refresh-at").text(
                lastKitRefreshAtFormatted,
              );
            }

            pollTimer = null;
            return;
          }

          // Not done yet: wait, then poll again
          pollTimer = setTimeout(doPoll, delayMs);
        })
        .fail(function (jqXHR) {
          $("#fontawesome-elementor-addon-kit-setup-spinner").hide();
          $("#fontawesome-elementor-addon-kit-setup-status-progress").hide();
          $("#fontawesome-elementor-addon-kit-setup-status-fail").show();
          handleAjaxFailure("status", jqXHR);
          setBusy(false);
          pollTimer = null;
        });
    }

    // Kick off the first poll immediately
    doPoll();
  }

  $(function () {
    $("#fontawesome-elementor-addon-kit-setup-start").on("click", function () {
      if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
      }

      setBusy(true);
      clearErrors();
      $("#fontawesome-elementor-addon-kit-setup-status-fail").hide();
      $("#fontawesome-elementor-addon-kit-setup-status-success").hide();
      $("#fontawesome-elementor-addon-kit-setup-status-progress").text(
        "Starting…",
      );
      $("#fontawesome-elementor-addon-kit-setup-status-progress").show();
      $("#fontawesome-elementor-addon-kit-setup-spinner").show();

      $.post(FontawesomeElementorAddonAdmin.ajaxurl, {
        action: "fontawesome_elementor_addon_kit_setup_start",
        nonce: FontawesomeElementorAddonAdmin.nonce,
      })
        .done(function (resp) {
          if (!resp || !resp.success) {
            $("#fontawesome-elementor-addon-kit-setup-status-progress").hide();
            $("#fontawesome-elementor-addon-kit-setup-status-fail").show();
            displayErrors((resp && resp.data) || "Kit setup failed to start.");
            setBusy(false);
            return;
          }
          const buildId = resp.data.build_id;
          $("#fontawesome-elementor-addon-kit-setup-status-progress").text(
            "Running…",
          );
          poll(buildId);
        })
        .fail(function (jqXHR) {
          $("#fontawesome-elementor-addon-kit-setup-spinner").hide();
          $("#fontawesome-elementor-addon-kit-setup-status-progress").hide();
          $("#fontawesome-elementor-addon-kit-setup-status-fail").show();
          handleAjaxFailure("start", jqXHR);
          setBusy(false);
        });
    });
  });
})(jQuery);
