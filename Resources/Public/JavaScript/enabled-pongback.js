import AjaxRequest from "@typo3/core/ajax/ajax-request.js";

const checkboxes = document.querySelectorAll('input[type="checkbox"][data-pongback-uid]');


checkboxes.forEach(function (checkbox) {
  checkbox.addEventListener('change', function () {
    const pongbackUid = this.getAttribute('data-pongback-uid');
    const isChecked = this.checked;
    new AjaxRequest(TYPO3.settings.ajaxUrls.pongback_enabled_pingback)
      .withQueryArguments({
        uid: pongbackUid,
        enabled: isChecked
      })
      .get()
      .then(async function (response) {
        const resolved = await response.resolve();
        console.log(response);
      });
  });
});
