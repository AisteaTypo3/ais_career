(function () {
  function updateResults(htmlText) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(htmlText, 'text/html');
    var newResults = doc.querySelector('.aiscareer-results');
    var currentResults = document.querySelector('.aiscareer-results');
    if (newResults && currentResults) {
      currentResults.innerHTML = newResults.innerHTML;
    }
  }

  function ajaxSubmit(form) {
    if (!form) {
      return;
    }
    var formData = new FormData(form);
    var actionUrl = form.action;

    fetch(actionUrl, {
      method: 'POST',
      body: formData,
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (response) { return response.text(); })
      .then(function (htmlText) {
        updateResults(htmlText);
        highlightAvailableCountries();
      })
      .catch(function () {
        form.submit();
      });
  }

  function bindFilterForm() {
    var form = document.querySelector('.aiscareer-filters form');
    if (!form) {
      return;
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      ajaxSubmit(form);
    });

    var inputs = form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]');
    inputs.forEach(function (el) {
      el.addEventListener('change', function () {
        ajaxSubmit(form);
      });
    });
  }

  // Fertige Lösung: verfügbare Länder im SVG markieren + beim Klick Country-Filter (Select) setzen
  function highlightAvailableCountries() {
    // Dropdown-Element holen (im Haupt-DOM)
    var countrySelect = document.getElementById('aiscareer-country');

    if (!countrySelect) {
      console.warn('Country dropdown not found');
      return;
    }

    // Alle verfügbaren Ländercodes aus dem Dropdown sammeln
    var availableCountries = [];
    var options = countrySelect.querySelectorAll('option');

    options.forEach(function (option) {
      var countryCode = option.value;
      // Leere / "All"-Option überspringen
      if (countryCode && countryCode !== '') {
        availableCountries.push(countryCode);
      }
    });

    // SVG-Object-Element finden
    var svgObject = document.querySelector('object[type="image/svg+xml"]');

    if (!svgObject) {
      console.warn('SVG object not found');
      return;
    }

    // Helper: Country Filter setzen (im Haupt-DOM)
    function setCountryFilter(countryCode) {
      var select = document.getElementById('aiscareer-country');
      if (!select) {
        return;
      }

      select.value = countryCode;

      // Change-Event triggern, damit vorhandene Listener/Filter-Logik feuert
      select.dispatchEvent(new Event('change', { bubbles: true }));

      console.log('Country Filter gesetzt auf: ' + countryCode);
    }

    // Logik, die nach dem Laden des SVG ausgeführt wird
    function onSvgLoaded() {
      var svgDoc = svgObject.contentDocument;

      if (!svgDoc) {
        console.warn('Could not access SVG document');
        return;
      }

      var svgRoot = svgDoc.querySelector('svg');
      if (!svgRoot) {
        console.warn('No <svg> root found in SVG document');
        return;
      }

      // CSS-Styles direkt in das SVG-Dokument einfügen
      var styleElement = svgDoc.querySelector('#country-highlight-styles');
      if (!styleElement) {
        styleElement = svgDoc.createElementNS('http://www.w3.org/2000/svg', 'style');
        styleElement.id = 'country-highlight-styles';
        styleElement.textContent =
          '.available-country path { fill: #4CAF50 !important; cursor: pointer; transition: fill 0.3s ease; }' +
          '.available-country:hover path { fill: #45a049 !important; }' +
          '.available-country.active path { fill: #2e7d32 !important; }';
        svgRoot.appendChild(styleElement);
      }

      // Länder markieren + Click-Handler setzen
      var markedCount = 0;

      availableCountries.forEach(function (countryCode) {
        var svgElement = svgDoc.getElementById(countryCode);

        if (svgElement) {
          svgElement.classList.add('available-country');

          // Für den Fall, dass die ID direkt auf einem <path> liegt:
          if (svgElement.tagName && svgElement.tagName.toLowerCase() === 'path') {
            svgElement.style.cursor = 'pointer';
          }

          // Click: aktiv markieren + Filter setzen
          svgElement.addEventListener('click', function () {
            // Active-Klasse entfernen
            var activeNodes = svgDoc.querySelectorAll('.available-country');
            activeNodes.forEach(function (el) {
              el.classList.remove('active');
            });

            svgElement.classList.add('active');
            setCountryFilter(countryCode);
          });

          markedCount++;
          console.log('Land ' + countryCode + ' markiert');
        } else {
          console.warn('SVG-Element für ' + countryCode + ' nicht gefunden');
        }
      });

      console.log(markedCount + ' Länder markiert:', availableCountries);
    }

    // Nur einmal binden (falls Funktion mehrfach läuft)
    svgObject.removeEventListener('load', onSvgLoaded);
    svgObject.addEventListener('load', onSvgLoaded);

    // Falls das Object bereits geladen ist, direkt ausführen
    if (svgObject.contentDocument && svgObject.contentDocument.querySelector('svg')) {
      onSvgLoaded();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      bindFilterForm();
      highlightAvailableCountries();
    });
  } else {
    bindFilterForm();
    highlightAvailableCountries();
  }
})();
