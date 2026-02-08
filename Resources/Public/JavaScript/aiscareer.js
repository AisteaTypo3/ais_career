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

  function resetFilters(form) {
    if (!form) {
      return;
    }
    var selects = form.querySelectorAll('select');
    selects.forEach(function (el) {
      el.value = '';
    });
    var checks = form.querySelectorAll('input[type=\"checkbox\"], input[type=\"radio\"]');
    checks.forEach(function (el) {
      el.checked = false;
    });

    ajaxSubmit(form);

    var results = document.querySelector('.aiscareer-results');
    if (results && results.getAttribute('data-list-url')) {
      var cleanUrl = results.getAttribute('data-list-url');
      if (cleanUrl) {
        try {
          window.history.replaceState({}, '', cleanUrl);
        } catch (e) {
          // ignore history errors
        }
      }
    }
  }

  function bindFilterForm() {
    var form = document.querySelector('.aiscareer-filters form');
    if (!form) {
      return;
    }

    var resetButton = form.querySelector('.aiscareer-reset');
    if (resetButton) {
      resetButton.addEventListener('click', function (event) {
        event.preventDefault();
        resetFilters(form);
      });
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

  function applyViewMode(view) {
    var results = document.querySelector('.aiscareer-results');
    if (!results) {
      return;
    }
    results.classList.toggle('is-list', view === 'list');
    results.classList.toggle('is-grid', view === 'grid');

    var buttons = document.querySelectorAll('.aiscareer-toggle-button');
    buttons.forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-view') === view);
    });
  }

  function bindViewToggle() {
    var buttons = document.querySelectorAll('.aiscareer-toggle-button');
    if (!buttons.length) {
      return;
    }
    var stored = null;
    try {
      stored = localStorage.getItem('aiscareerView');
    } catch (e) {
      stored = null;
    }
    var initial = stored === 'list' || stored === 'grid' ? stored : 'grid';
    applyViewMode(initial);

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var view = btn.getAttribute('data-view');
        if (view !== 'list' && view !== 'grid') {
          return;
        }
        applyViewMode(view);
        try {
          localStorage.setItem('aiscareerView', view);
        } catch (e) {
          // ignore storage errors
        }
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

    function resolveMapColors() {
      var root = document.querySelector('.aiscareer') || document.documentElement;
      var styles = getComputedStyle(root);
      var available = styles.getPropertyValue('--aiscareer-map-available').trim() || '#4caf50';
      var hover = styles.getPropertyValue('--aiscareer-map-hover').trim() || '#45a049';
      var active = styles.getPropertyValue('--aiscareer-map-active').trim() || '#2e7d32';
      return { available: available, hover: hover, active: active };
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
        var colors = resolveMapColors();
        styleElement = svgDoc.createElementNS('http://www.w3.org/2000/svg', 'style');
        styleElement.id = 'country-highlight-styles';
        styleElement.textContent =
          '.available-country path { fill: ' + colors.available + ' !important; cursor: pointer; transition: fill 0.3s ease; }' +
          '.available-country:hover path { fill: ' + colors.hover + ' !important; }' +
          '.available-country.active path { fill: ' + colors.active + ' !important; }';
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
      bindViewToggle();
      highlightAvailableCountries();
    });
  } else {
    bindFilterForm();
    bindViewToggle();
    highlightAvailableCountries();
  }
})();
