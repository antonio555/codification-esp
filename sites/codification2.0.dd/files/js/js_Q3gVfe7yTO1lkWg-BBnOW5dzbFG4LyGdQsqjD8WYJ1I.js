/**
 * @file
 * JavaScript behaviors for Geocomplete location integration.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  // @see https://ubilabs.github.io/geocomplete/
  // @see https://developers.google.com/maps/documentation/javascript/reference?csw=1#MapOptions
  Drupal.webform = Drupal.webform || {};
  Drupal.webform.locationGeocomplete = Drupal.webform.locationGeocomplete || {};
  Drupal.webform.locationGeocomplete.options = Drupal.webform.locationGeocomplete.options || {};

  /**
   * Initialize location Geocompletion.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformLocationGeocomplete = {
    attach: function (context) {
      if (!$.fn.geocomplete) {
        return;
      }

      $(context).find('div.js-form-type-webform-location').once('webform-location').each(function () {
        var $element = $(this);
        var $input = $element.find('.webform-location-geocomplete');
        var $map = null;
        if ($input.attr('data-webform-location-map')) {
          $map = $('<div class="webform-location-map"><div class="webform-location-map--container"></div></div>').insertAfter($input).find('.webform-location-map--container');
        }

        var options = $.extend({
          details: $element,
          detailsAttribute: 'data-webform-location-attribute',
          types: ['geocode'],
          map: $map,
          mapOptions: {
            disableDefaultUI: true,
            zoomControl: true
          }
        }, Drupal.webform.locationGeocomplete.options);

        var $geocomplete = $input.geocomplete(options);

        $geocomplete.on('input', function () {
          // Reset attributes on input.
          $element.find('[data-webform-location-attribute]').val('');
        }).on('blur', function () {
          // Make sure to get attributes on blur.
          if ($element.find('[data-webform-location-attribute="location"]').val() === '') {
            var value = $geocomplete.val();
            if (value) {
              $geocomplete.geocomplete('find', value);
            }
          }
        });

        // If there is default value look up location's attributes, else see if
        // the default value should be set to the browser's current geolocation.
        var value = $geocomplete.val();
        if (value) {
          $geocomplete.geocomplete('find', value);
        }
        else if (navigator.geolocation && $geocomplete.attr('data-webform-location-geolocation')) {
          navigator.geolocation.getCurrentPosition(function (position) {
            $geocomplete.geocomplete('find', position.coords.latitude + ', ' + position.coords.longitude);
          });
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
;
/**
 * @file
 * JavaScript behaviors for message element integration.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Move show weight to after the table.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformMultiple = {
    attach: function (context, settings) {
      for (var base in settings.tableDrag) {
        if (settings.tableDrag.hasOwnProperty(base)) {
          var $tableDrag = $(context).find('#' + base);
          var $toggleWeight = $tableDrag.parent().find('.tabledrag-toggle-weight');
          $toggleWeight.addClass('webform-multiple-tabledrag-toggle-weight');
          $tableDrag.after($toggleWeight);
        }
      }
    }
  };

})(jQuery, Drupal);
;
/**
 * @file
 * JavaScript behaviors for terms of service.
 */

(function ($, Drupal) {

  'use strict';

  // @see http://api.jqueryui.com/dialog/
  Drupal.webform = Drupal.webform || {};
  Drupal.webform.termsOfServiceModal = Drupal.webform.termsOfServiceModal || {};
  Drupal.webform.termsOfServiceModal.options = Drupal.webform.termsOfServiceModal.options || {};

  /**
   * Initialize terms of service element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformTermsOfService = {
    attach: function (context) {
      $(context).find('.js-form-type-webform-terms-of-service').once('webform-terms-of-service').each(function () {
        var $element = $(this);
        var type = $element.attr('data-webform-terms-of-service-type');

        var $details = $element.find('.webform-terms-of-service-details');

        // Initialize the modal.
        if (type === 'modal') {
          // Move details title to attribute.
          var $title = $element.find('.webform-terms-of-service-details--title');
          if ($title.length) {
            $details.attr('title', $title.text());
            $title.remove();
          }

          var options = $.extend({
            modal: true,
            autoOpen: false,
            minWidth: 600,
            maxWidth: 800
          }, Drupal.webform.termsOfServiceModal.options);
          $details.dialog(options);
        }

        $element.find('label a').click(function (event) {
          if (type === 'modal') {
            $details.dialog('open');
          }
          else {
            $details.slideToggle();
          }
          event.preventDefault();
        });
      });
    }
  };

})(jQuery, Drupal);
;
