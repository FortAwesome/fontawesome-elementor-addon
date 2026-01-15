(function ($) {
  let pollTimer = null;

  function setBusy(isBusy) {
    $("#fontawesome-elementor-addon-kit-setup-start").prop("disabled", isBusy);
    $("#fontawesome-elementor-addon-kit-setup-spinner").toggleClass(
      "is-active",
      isBusy,
    );
  }

  function poll(buildId) {
    pollTimer = setInterval(function () {
      $.post(FontawesomeElementorAddonAdmin.ajaxurl, {
        action: "fontawesome_elementor_addon_kit_setup_status",
        nonce: FontawesomeElementorAddonAdmin.nonce,
        build_id: buildId,
      })
        .done(function (resp) {
          if (!resp || !resp.success) {
            $("#fontawesome-elementor-addon-kit-setup-status").text(
              "Error checking status.",
            );
            setBusy(false);
            clearInterval(pollTimer);
            return;
          }

          const data = resp.data;
          $("#fontawesome-elementor-addon-kit-setup-status").text(
            data.message || data.status,
          );

          if (data.done) {
            setBusy(false);
            clearInterval(pollTimer);
            $("#fontawesome-elementor-addon-kit-setup-status").text("Done.");
          }
        })
        .fail(function () {
          $("#fontawesome-elementor-addon-kit-setup-status").text(
            "Request failed.",
          );
          setBusy(false);
          clearInterval(pollTimer);
        });
    }, 1000);
  }

  $(function () {
    $("#fontawesome-elementor-addon-kit-setup-start").on("click", function () {
      if (pollTimer) clearInterval(pollTimer);

      setBusy(true);
      $("#fontawesome-elementor-addon-kit-setup-status").text("Starting…");

      $.post(FontawesomeElementorAddonAdmin.ajaxurl, {
        action: "fontawesome_elementor_addon_kit_setup_start",
        nonce: FontawesomeElementorAddonAdmin.nonce,
      })
        .done(function (resp) {
          if (!resp || !resp.success) {
            $("#fontawesome-elementor-addon-kit-setup-status").text(
              "Failed to start.",
            );
            setBusy(false);
            return;
          }
          const buildId = resp.data.build_id;
          $("#fontawesome-elementor-addon-kit-setup-status").text("Running…");
          poll(buildId);
        })
        .fail(function () {
          $("#fontawesome-elementor-addon-kit-setup-status").text(
            "Start request failed.",
          );
          setBusy(false);
        });
    });
  });
})(jQuery);
