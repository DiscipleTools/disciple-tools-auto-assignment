jQuery(function ($) {

  /**
   * EVENT LISTENERS
   */
  $(document).ready(function () {
    load_options();
  });

  $(document).on('click', '#aa_general_main_col_settings_update_but', function () {
    save_options();
  });

  /**
   * HELPER FUNCTIONS
   */
  function load_options() {
    let msg_ele = $('#aa_general_main_col_msg');
    msg_ele.fadeOut('fast');

    execute_endpoint_request('general', {}, window.dt_auto_assign.dt_endpoint_load, 'GET', function (response) {

      if (response && response['success']) {

        let auto_assign_minors_ele = $('#aa_general_main_col_settings_auto_assign_minors');
        let match_on_gender_ele = $('#aa_general_main_col_settings_enforce_match_on_gender');
        let match_on_location_ele = $('#aa_general_main_col_settings_enforce_match_on_location');
        let match_on_language_ele = $('#aa_general_main_col_settings_enforce_match_on_language');

        // Settings config - revert to defaults if unavailable
        if (response['has_settings']) {

          let settings_config = JSON.parse(response['settings']);

          auto_assign_minors_ele.prop('checked', JSON.parse(settings_config['auto_assign_minors']));
          match_on_gender_ele.prop('checked', JSON.parse(settings_config['enforce_gender']));
          match_on_location_ele.prop('checked', JSON.parse(settings_config['enforce_location']));
          match_on_language_ele.prop('checked', JSON.parse(settings_config['enforce_language']));

        } else {

          auto_assign_minors_ele.prop('checked', false);
          match_on_gender_ele.prop('checked', true);
          match_on_location_ele.prop('checked', true);
          match_on_language_ele.prop('checked', false);

          // Force automated saving of defaults....
          save_options();

        }

      } else {
        console.log(response);

        msg_ele.html('Unable to load setting options.');
        msg_ele.fadeIn('fast');
      }
    });
  }

  function save_options() {
    let payload = {
      auto_assign_minors: $('#aa_general_main_col_settings_auto_assign_minors').prop('checked'),
      enforce_gender: $('#aa_general_main_col_settings_enforce_match_on_gender').prop('checked'),
      enforce_location: $('#aa_general_main_col_settings_enforce_match_on_location').prop('checked'),
      enforce_language: $('#aa_general_main_col_settings_enforce_match_on_language').prop('checked')
    };

    let msg_ele = $('#aa_general_main_col_msg');
    msg_ele.fadeOut('fast');

    execute_endpoint_request('general', payload, window.dt_auto_assign.dt_endpoint_save, 'POST', function (response) {
      if (response && response['success']) {
        msg_ele.html('Successfully saved setting options.');

      } else {
        msg_ele.html('Unable to save setting options.');
      }

      msg_ele.fadeIn('fast');
    });
  }

  function execute_endpoint_request(action, data, url, method, callback) {
    let payload = {
      action: action,
      data: data
    };

    $.ajax({
      url: url,
      method: method,
      data: payload,
      beforeSend: (xhr) => {
        xhr.setRequestHeader("X-WP-Nonce", window.dt_auto_assign.dt_endpoint_nonce);
      },
      success: function (response) {
        callback(response);
      },
      error: function (response) {
        console.log(response);
      }
    });

  }
});
