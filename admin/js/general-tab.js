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

  $(document).on('click', '#aa_general_main_col_sources_update_but', function () {
    save_options();
  });

  $(document).on('change', '#aa_general_main_col_sources_current_list_select', function () {
    update_select_sources_state();
  });

  $(document).on('click', '#aa_general_main_col_sources_current_list_select_add', function () {
    add_selected_source();
  });

  $(document).on('click', '.aa-general-main-col-sources-table-row-remove-but', function (e) {
    remove_selected_source(e);
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

        let sources_select_ele = $('#aa_general_main_col_sources_current_list_select');
        let sources_table_ele = $('#aa_general_main_col_sources_table');

        // Settings config - revert to defaults if unavailable
        if (response['has_settings']) {

          let settings_config = response['settings'];

          auto_assign_minors_ele.prop('checked', JSON.parse(settings_config['auto_assign_minors']));
          match_on_gender_ele.prop('checked', JSON.parse(settings_config['enforce_gender']));
          match_on_location_ele.prop('checked', JSON.parse(settings_config['enforce_location']));
          match_on_language_ele.prop('checked', JSON.parse(settings_config['enforce_language']));

          // Display previously selected sources
          let sources = settings_config['sources'];
          if (sources) {
            sources.forEach(function (source, idx) {
              let html = build_new_source_row_html(source['key'], source['label']);

              if (html) {
                sources_table_ele.find('tbody:last').append(html);
              }
            });
          }

          // Determine if all sources are to be supported
          if (settings_config['support_all_sources'] && JSON.parse(settings_config['support_all_sources'])) {
            sources_select_ele.val('all');
          }

        } else {

          auto_assign_minors_ele.prop('checked', false);
          match_on_gender_ele.prop('checked', true);
          match_on_location_ele.prop('checked', true);
          match_on_language_ele.prop('checked', false);

          sources_select_ele.val('all');

          // Force automated saving of defaults....
          save_options();

        }

        // Adjust selected sources states accordingly
        update_select_sources_state();

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
      enforce_language: $('#aa_general_main_col_settings_enforce_match_on_language').prop('checked'),

      support_all_sources: ($('#aa_general_main_col_sources_current_list_select').val() === 'all'),
      sources: fetch_selected_sources()
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

  function fetch_selected_sources() {
    let sources = [];
    $('#aa_general_main_col_sources_table').find('tbody > tr').each(function (idx, tr) {
      let key = $(tr).find('#aa_general_main_col_sources_table_source_key').val();
      let label = $(tr).find('#aa_general_main_col_sources_table_source_label').val();

      sources.push({
        'key': key,
        'label': label
      });
    });

    return sources;
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

  function update_select_sources_state() {
    let disabled = ($('#aa_general_main_col_sources_current_list_select').val() === 'all');

    $('#aa_general_main_col_sources_current_list_select_add').prop('disabled', disabled);
    $('#aa_general_main_col_sources_table').find('button').prop('disabled', disabled);
  }

  function add_selected_source() {
    let key = $('#aa_general_main_col_sources_current_list_select').val();
    let label = $('#aa_general_main_col_sources_current_list_select option:selected').text();

    if (key && !already_has_source(key)) {
      let html = build_new_source_row_html(key, label);

      if (html) {
        $('#aa_general_main_col_sources_table').find('tbody:last').append(html);
      }
    }
  }

  function already_has_source(key) {
    let hits = $('#aa_general_main_col_sources_table').find('tbody > tr').filter(function (idx) {
      return key === $(this).find('#aa_general_main_col_sources_table_source_key').val();
    });

    return (hits && hits.size() > 0);
  }

  function build_new_source_row_html(key, label) {
    if (key && label) {
      return `<tr>
                  <td style="vertical-align: middle;">
                  <input id="aa_general_main_col_sources_table_source_key" type="hidden" value="${window.lodash.escape(key)}"/>
                  <input id="aa_general_main_col_sources_table_source_label" type="hidden" value="${window.lodash.escape(label)}"/>
                  ${window.lodash.escape(key)}
                  </td>
                  <td style="vertical-align: middle;">${window.lodash.escape(label)}</td>
                  <td style="vertical-align: middle;">
                    <span style="float:right;">
                        <button type="submit" class="button float-right aa-general-main-col-sources-table-row-remove-but">Remove</button>
                    </span>
                  </td>
                </tr>`;
    }
    return null;
  }

  function remove_selected_source(evt) {
    let row = evt.currentTarget.parentNode.parentNode.parentNode;
    row.parentNode.removeChild(row);
  }
});
