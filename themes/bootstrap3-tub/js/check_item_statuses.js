/**
 * Create a simple button for an action
 *
 * @param href          \b STR  Link to open on click
 * @param hover         \b STR  Title to show on hovering the link
 * @param text          \b STR  The link text
 * @param icon          \b STR  Some FontAwesome icon
 * @param css_classes   \b STR  All links get the classes "holdlink"
 *                              (+ the icon param); add some special class
 * @param target        \b STR  opt: target for link; leave empty for self
 * @param id            \b STR  opt: element id
 * @param custom        \b STR  opt: any other a tag parameter part
 *
 * @todo: id and custom only added for cart - not too nice
 *
 * @return \b STR link html
 */
function create_button(href, hover, text, icon, css_classes, target, id, custom) {
  //target = target || 'undefined';
  target = (typeof target !== 'undefined') ? 'target="'+target+'"' : '';
  id     = (typeof id     !== 'undefined') ? 'id="'+id+'"' : '';
  custom = custom || '';
  var button;

  button    = '<a href="'+href+'" '+id+' rel="tooltip" title="'+hover+'" class="holdlink '+css_classes+'" '+target+' '+custom+'><i class="fa '+icon+'"></i> ' + text + '</a>';

  return button;
}

function create_simple_button(id, loc_code, link_title, modal_title, modal_body, iframe_src, modal_foot, icon_class, icon, text, modal_suffix = '') {
  // Set function defaults if empty
  iframe_src = iframe_src || '';
  modal_foot = modal_foot || '';
  icon_class = icon_class || 'tub_fa-info_p';
  icon = icon || 'fa-info-circle';
  text = text || '';

  var href_class = '';
  var modal;
  var iframe = '';


  modal = '<a href="'+iframe_src+'" rel="tooltip" title="' + link_title + '" class="holdlink modal-link hidden-print"><i class="fa '+icon+' '+icon_class+'"></i> ' + text + '</a>';

  return modal;
}

/**
 * Create a generic "Infomation" modal by function or a button.
 *
 * @note: It becomes a button if parameter text is supplied, otherwise the "i"-icon is used.
 *
 * Creating the modals inline got messy. This function isn't beautiful as well,
 * but for now the better way.
 *
 * @note: 2015-09-29: Argh, only Firefox support default values for paramters
 * (https://stackoverflow.com/questions/19699257/uncaught-syntaxerror-unexpected-token-in-google-chrome/19699282#19699282)
 *
 * @param id            \b STR  Some unique id for the modal (not used yet)
 * @param loc_code      \b STR  Used by $(document).ready bewlow; use some
 *                              speaking name (besides the location abbrevation
 *                              like LS1 etc., currently used: Multi, Magazin, Loaned, Unknown; ext_ill, ext_acqusition; holddirectdl)
 * @param link_title    \b STR  The title displayed on hovering the modal
 * @param modal_title   \b STR  The title displayed in the modal header
 * @param modal_body    \b STR  The modal "body"
 * @param iframe_src    \b STR  optional: if you add an url, it will be loaded in
 *                              an iframe below the modal_body part
 * @param modal_foot    \b STR  ERRRRM - not used really...
 * @param icon_class    \b STR  optional: the link to open a modal has always the
 *                              classes "fa fa-info-circle". Add a custom one
 * @param icon          \b STR  optional: Icon before button text
 * @param text          \b STR  optional: Button text
 *
 * @return \b STR modal html
 */
function create_modal(id, loc_code, link_title, modal_title, modal_body, iframe_src, modal_foot, icon_class, icon, text, modal_suffix = '') {
  // Set function defaults if empty
  iframe_src = iframe_src || '';
  modal_foot = modal_foot || '';
  icon_class = icon_class || 'tub_fa-info_p';
  icon = icon || 'fa-info-circle';
  text = text || '';

  var href_class = '';
  var modal;
  var iframe = '';

  // If text is given, add our Button class. Otherwise it's just an info icon
  if (text != '') href_class = 'holdlink';


  if (iframe_src != '') {
    iframe = ' data-iframe="'+iframe_src+'" ';
  }

  modal = '<a href="#" id="info-'+id+modal_suffix+'" rel="tooltip" title="' + link_title + '" data-lightbox class="locationInfox '+href_class+' modal-link hidden-print"><i class="fa '+icon+' '+icon_class+'"></i> ' + text + '<span data-title="' + modal_title + '" data-location="' + loc_code +'" '+iframe+' class="modal-dialog hidden">'+modal_body+modal_foot+'</span></a>';

  return modal;
}

function create_paia_modal(id, loc_code, link_title, modal_title, modal_body, href, modal_foot, icon_class, icon, text) {
  // Set function defaults if empty
  icon_class = icon_class || 'tub_fa-info_p';
  icon = icon || 'fa-info-circle';
  text = text || '';

  // If text is given, add our Button class. Otherwise it's just an info icon
  if (text != '') href_class = 'holdlink';

  modal = '<div><a class="locationInfox holdlink modal-link hidden-print" rel="tooltip" title="' + link_title + '" data-lightbox href="'+href+'"><i class="fa '+icon+' '+icon_class+'"></i>&nbsp;'+text+'</a><span data-title="' + modal_title + '" data-location="' + loc_code +'" class="modal-dialog hidden">'+modal_body+modal_foot+'</span></div>';

  return modal;
}

/*global Hunt, VuFind */
VuFind.register('itemStatuses', function ItemStatuses() {
  function linkCallnumbers(callnumber, callnumber_handler) {
    if (callnumber_handler) {
      var cns = callnumber.split(',\t');
      for (var i = 0; i < cns.length; i++) {
        cns[i] = '<a href="' + VuFind.path + '/Alphabrowse/Home?source=' + encodeURI(callnumber_handler) + '&amp;from=' + encodeURI(cns[i]) + '">' + cns[i] + '</a>';
      }
      return cns.join(',\t');
    }
    return callnumber;
  }
  function displayItemStatus(result, $item) {
    $item.addClass('js-item-done').removeClass('js-item-pending');
    $item.find('.status').empty().append(result.availability_message);
    $item.find('.ajax-availability').removeClass('ajax-availability hidden');
    if (typeof(result.error) != 'undefined'
          && result.error.length > 0
    ) {
      $item.find('.callnumAndLocation').empty().addClass('text-danger').append(result.error);
      $item.find('.callnumber,.hideIfDetailed,.location').addClass('hidden');
    } else if (typeof(result.full_status) != 'undefined'
          && result.full_status.length > 0
          && $item.find('.callnumAndLocation').length > 0
    ) {
      // Full status mode is on -- display the HTML and hide extraneous junk:
      $item.find('.callnumAndLocation').empty().append(result.full_status);
      $item.find('.callnumber,.hideIfDetailed,.location,.status').addClass('hidden');
    } else if (typeof(result.missing_data) !== 'undefined'
          && result.missing_data
    ) {
      // No data is available -- hide the entire status area:
      $item.find('.callnumAndLocation,.status,.holdlocation').addClass('hidden');
    } else if (result.locationList) {
      // We have multiple locations -- build appropriate HTML and hide unwanted labels:
      $item.find('.callnumber,.hideIfDetailed,.location').addClass('hidden');
      var locationListHTML = "";
      for (var x = 0; x < result.locationList.length; x++) {
        locationListHTML += '<div class="groupLocation">';
        if (result.locationList[x].availability) {
          locationListHTML += '<span class="text-success"><i class="fa fa-ok" aria-hidden="true"></i> '
                      + result.locationList[x].location + '</span> ';
        } else if (typeof(result.locationList[x].status_unknown) !== 'undefined'
                  && result.locationList[x].status_unknown
        ) {
          if (result.locationList[x].location) {
            locationListHTML += '<span class="text-warning"><i class="fa fa-status-unknown" aria-hidden="true"></i> '
                          + result.locationList[x].location + '</span> ';
          }
        } else {
          locationListHTML += '<span class="text-danger"><i class="fa fa-remove" aria-hidden="true"></i> '
                      + result.locationList[x].location + '</span> ';
        }
        locationListHTML += '</div>';
        locationListHTML += '<div class="groupCallnumber">';
        locationListHTML += (result.locationList[x].callnumbers)
          ? linkCallnumbers(result.locationList[x].callnumbers, result.locationList[x].callnumber_handler) : '';
        locationListHTML += '</div>';
      }
      $item.find('.locationDetails').removeClass('hidden');
      $item.find('.locationDetails').html(locationListHTML);
    } else {
          var loc_abbr;
          var loc_button;
          var loc_shelf  = result.callnumber.substring(0,2);
          var loc_callno = result.callnumber;
          var loc_modal_title = VuFind.translate('loc_modal_Title_shelf_generic') + loc_callno + ' (' + result.bestOptionLocation + ')';
          var loc_modal_body;
          // Add some additional infos for TUBHH holdings
          if (result.bestOptionLocation.indexOf('Lehr') > -1 || result.bestOptionLocation.indexOf('Text Book Collection') > -1) {
            loc_abbr = 'LBS';  loc_modal_body = VuFind.translate('loc_modal_Body_shelf_lbs') + result.bestOptionLocation + '.';
          }
          else if (result.bestOptionLocation.indexOf('Semesterapparat') > -1 || result.bestOptionLocation.indexOf('Course Reserve') > -1) {
            loc_abbr = 'SEM';  loc_modal_body = VuFind.translate('loc_modal_Body_sem') + '.';
          }
          else if (result.bestOptionLocation.indexOf('Lesesaal 1') > -1 || result.bestOptionLocation.indexOf('Reading Room 1') > -1 || result.bestOptionLocation.indexOf('reading room 1') > -1) {
            loc_abbr = 'LS1';  loc_modal_body = VuFind.translate('loc_modal_Body_shelf_ls') + result.bestOptionLocation + '.';
          }
          else if (result.bestOptionLocation.indexOf('Lesesaal 2') > -1 || result.bestOptionLocation.indexOf('Reading Room 2') > -1) {
            loc_abbr = 'LS2';  loc_modal_body = VuFind.translate('loc_modal_Body_shelf_ls') + result.bestOptionLocation + '.';
          }
          else if (result.bestOptionLocation.indexOf('Sonderstandort') > -1 || result.bestOptionLocation.indexOf('Special Location') > -1) {
            loc_abbr = 'SO';    loc_modal_body = VuFind.translate('loc_modal_Title_service_da');
          }
          else if (result.bestOptionLocation.indexOf('Arbeitsbereich') > -1) {
            loc_abbr = 'DA';    loc_modal_body = VuFind.translate('loc_modal_Title_service_da'); result.patronBestOption = 'service_desk_da';
          }
          else if (result.bestOptionLocation.indexOf('Voranmeldung') > -1 || result.bestOptionLocation.indexOf('Advance Reservation') > -1) {
/*            loc_abbr = 'SOV';    loc_modal_body = vufindString.loc_modal_Body_restricted; */
            result.patronBestOption = "advance_reservation";
          }
          else if (result.bestOptionLocation.indexOf('Fachreferent') > -1 || result.bestOptionLocation.indexOf('subject librarian') > -1) {
/*            loc_abbr = 'SOV';    loc_modal_body = vufindString.loc_modal_Body_restricted; */
            result.patronBestOption = "advance_reservation_fr";
          }
/*
          else if (result.bestOptionLocation.indexOf('Magazin') > -1 || result.bestOptionLocation.indexOf('Closed Stack') > -1) {
            loc_abbr = 'Transport';    loc_modal_body = vufindString.loc_modal_Title_service_transport;
          }
*/
          /* 2015-10-01 added @see http://redmine.tub.tuhh.de/issues/624 */
          else if (result.electronic == '1' && (result.locHref !== '' && result.locHref !== false)) {
            loc_abbr = 'Web';  loc_modal_body = VuFind.translate('loc_modal_Body_eMarc21');
          }
          // Electronic, but without link; pretty special case. It IS electronic, but let's handle it as false
          // Example case (cd rom): http://lincl1.b.tu-harburg.de:81/vufind2-test/Record/268707642
          else if (result.electronic == '1') {
            loc_abbr = 'DIGfail';  loc_modal_body = VuFind.translate('loc_modal_Body_eonly');
            result.patronBestOption = false;
          }
          else if (result.bestOptionLocation.indexOf('Shipping') > -1) {
            loc_abbr = 'ACQ';  loc_modal_body = VuFind.translate('loc_modal_Body_acquired');
          }
          // 2015-12-17: Addes case for periodical supplementals ("Einzelsig." but not multiVols)
          // @todo: this should be solved more thoroughly finally
          else if (result.bestOptionLocation == 's. zugeh\u00f6rige Publikationen' && result.multiVols == false) {
            loc_abbr = 'Supplemental'; // might make it a special button; not used yet anywhere
            result.patronBestOption = 'false'; // override (always get the default button that is show if everything fails; see switch below)
          }
          /* This might happen if all physical copies are on loan and there is only the electronic version available */
          /* But it should not happen if there is an item in the closed Stack */
/*
          else if (result.electronic == '0' && (result.bestOptionLocation.indexOf('http') > -1 || (result.bestOptionLocation != 'Closed Stack' && result.bestOptionLocation != 'Magazin' && result.callnumber != 'Unknown' && result.duedate != '')) && result.locHref !== '' && result.locHref !== false) {
            loc_abbr = '';  result.patronBestOption = 'recall'; loc_modal_body = vufindString.loc_modal_Body_eMarc21;
          }
*/
          else {
            loc_abbr = 'Undefined';
            //alert('Hier ist ein komischer Fall bei '+loc_callno);
          }

            var bestOption = '';
            var fallbackOption = ''; // we need this, because the sfx button comes from another source - and we have to check, if it exists before adding buttons in some cases
            switch(result.patronBestOption) {
              case 'already_taken_by_patron':
                  title = VuFind.translate('loc_modal_Title_already_taken');
                  loc_modal_button = create_modal(id = result.id,
                                            loc_code    = 'Loaned',
                                            link_title  = title,
                                            modal_title = title,
                                            modal_body  = VuFind.translate('loc_modal_Body_already_taken'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'alreadytaken',
                                            icon        = 'fa-home',
                                            text        = VuFind.translate('already_taken'));
                  bestOption = bestOption + loc_modal_button;
                  break;
              case 'reserve_or_local':
                  title = loc_modal_body+ '\n' + VuFind.translate('loc_modal_Title_refonly_generic');
                  loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = title,
                                            modal_title = loc_modal_title,
                                            modal_body  = loc_modal_body+' ' + VuFind.translate('loc_modal_Title_refonly_generic'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'holdrefonly',
                                            icon        = 'fa-home',
                                            text        = loc_abbr + ' ' + loc_callno);
                  bestOption = bestOption + loc_modal_button;
                // just continue, don't break;
              case 'recall':
                title = VuFind.translate('loc_modal_Title_reserve_base');
                if (result.duedate) {
                    title += VuFind.translate('loc_modal_Title_reserve') + result.duedate;
                }
                if (result.placed_requests > 0) {
                    title += ' ' + VuFind.translate('loc_modal_Title_reservations') + result.placed_requests
                }
                loc_modal_button = create_paia_modal(id = result.id,
                                              loc_code    = 'Loaned',
                                              link_title  = title,
                                              modal_title = title,
                                              modal_body  = VuFind.translate('loc_modal_Body_reserve'),
                                              iframe_src  = result.bestOptionHref,
                                              modal_foot  = '',
                                              icon_class  = 'holdreserve',
                                              icon        = 'fa-clock-o',
                                              text        = VuFind.translate('recall_this'));
                bestOption = bestOption + loc_modal_button;
                break;
              case 'request_or_local':
                  title = loc_modal_body+ '\n' + VuFind.translate('loc_modal_Title_refonly_generic');
                  loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = title,
                                            modal_title = loc_modal_title,
                                            modal_body  = loc_modal_body+' ' + VuFind.translate('loc_modal_Title_refonly_generic'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'holdrefonly',
                                            icon        = 'fa-home',
                                            text        = loc_abbr + ' ' + loc_callno);
                  bestOption = bestOption + loc_modal_button;
                // just continue, don't break;
              case 'storageretrieval':
                if (result.presenceOnly == "1") {
                    icon = 'fa-home';
                }
                else {
                    icon = 'fa-upload';
                }
                loc_modal_button = create_paia_modal(id = result.id,
                                              loc_code    = result.bestOptionLocation,
                                              link_title  = VuFind.translate('loc_btn_Hover_order'),
                                              modal_title = VuFind.translate('loc_modal_Title_order'),
                                              modal_body  = VuFind.translate('loc_modal_Body_order'),
                                              iframe_src  = result.bestOptionHref,
                                              modal_foot  = '',
                                              icon_class  = 'holdorder',
                                              icon        = icon,
                                              text        = VuFind.translate('hold_place'));
                bestOption = bestOption + loc_modal_button;
                break;
            case 'see_copies':
                // Add button "See copies"
                loc_modal_button_vols = create_simple_button(id = result.id,
                                          loc_code    = 'Copies',
                                          link_title  = VuFind.translate('loc_btn_Hover_holdings'),
                                          modal_title = VuFind.translate('loc_modal_Title_holdings'),
                                          modal_body  = '',
                                          iframe_src  = '/Record/'+result.id,
                                          modal_foot  = '',
                                          icon_class  = 'holdtomes',
                                          icon        = 'fa-stack-overflow',
                                          text        = VuFind.translate('see_holdings'));
                bestOption = loc_modal_button_vols;
                break;
            case 'e_only':
              if (result.missing_data !== true && result.bestOptionLocation != 'Unknown' && result.locHref != '') {
                if (result.bestOptionLocation == "Internet") {
                    result.bestOptionLocation = "Web";
                }
                if (result.bestOptionLocation == "Web") {
                    loc_abbr = result.bestOptionLocation;
                }
                
                /* 2015-10-01 @see http://redmine.tub.tuhh.de/issues/624 */
                title = loc_abbr;
                if (result.bestOptionLocation == result.locHref) {
                  title_modal = title;
                } else {
                  title_modal = result.bestOptionLocation;
                }
                
                loc_button = create_button(href   = result.locHref,
                                           hover  = VuFind.translate('loc_modal_Title_eMarc21'),
                                           text   = title,
                                           icon   = 'fa-globe',
                                           css_classes = 'holdelectronic webbutton');
                loc_modal_link = create_modal(id          = result.id,
                                              loc_code    = loc_abbr,
                                              link_title  = VuFind.translate('infoIcon_Hover'),
                                              modal_title = VuFind.translate('loc_modal_Title_eMarc21') +': '+title_modal,
                                              modal_body  = VuFind.translate('loc_modal_Body_eMarc21'),
                                              iframe_src  = result.locHref,
                                              modal_foot  = '');

                //Dont's show loc_abbr = Web in addition to SFX-link
                fallbackOption = loc_button;
              }
              break;
            case 'shelf': //fa-hand-lizard-o is nice too (but only newest FA)
              loc_modal_button = create_modal(id          = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = loc_modal_body,
                                            modal_title = loc_modal_title,
                                            modal_body  = loc_modal_body,
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'holdshelf',
                                            icon        = 'fa-map-marker',
                                            text        = loc_abbr + ' ' + loc_callno);
              bestOption = loc_modal_button;
              break;
            case 'local':
              // Todo: is it necessary to use result.reference_callnumber and result.reference_location. It might be...?
              title = loc_modal_body+ '\n' + VuFind.translate('loc_modal_Title_refonly_generic');
              loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = title,
                                            modal_title = loc_modal_title,
                                            modal_body  = loc_modal_body+' ' + VuFind.translate('loc_modal_Title_refonly_generic'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'holdrefonly',
                                            icon        = 'fa-home',
                                            text        = loc_abbr + ' ' + loc_callno);
              bestOption = bestOption + loc_modal_button;
              break;
            case 'acquired':
              loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = VuFind.translate('loc_btn_Hover_acquired'),
                                            modal_title = VuFind.translate('loc_modal_Title_acquired'),
                                            modal_body  = loc_modal_body,
                                            iframe_src  = 'https://katalog.b.tuhh.de/DB=1/'+VuFind.translate('opclang')+'/PPN?PPN='+result.id,
                                            modal_foot  = '',
                                            icon_class  = 'holdacquired',
                                            icon        = 'fa-money',
                                            text        = VuFind.translate('loc_modal_Title_acquired'));
              bestOption = loc_modal_button;
              break;
            case 'service_desk':
              loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = VuFind.translate('loc_modal_Title_service_da'),
                                            modal_title = VuFind.translate('loc_modal_Title_service_da'),
                                            modal_body  = VuFind.translate('loc_modal_Body_service_da'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'x',
                                            icon        = 'fa-frown-o',
                                            text        = 'SO ' + loc_callno);
              bestOption = loc_modal_button;
              break;
            case 'service_desk_da':
              loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = VuFind.translate('loc_modal_Title_service_da'),
                                            modal_title = VuFind.translate('loc_modal_Title_service_da'),
                                            modal_body  = VuFind.translate('loc_modal_Body_service_da'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'x',
                                            icon        = 'fa-frown-o',
                                            text        = 'DA ' + loc_callno);
              bestOption = loc_modal_button;
              break;
            case 'askstaff':
              loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = VuFind.translate('loc_modal_Title_service_ask'),
                                            modal_title = VuFind.translate('loc_modal_Title_service_ask'),
                                            modal_body  = VuFind.translate('loc_modal_Body_service_ask'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'x',
                                            icon        = 'fa-frown-o',
                                            text        = '? ' + loc_callno);
              bestOption = loc_modal_button;
              break;
            case 'reserved_without_link':
              loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = VuFind.translate('loc_modal_Title_temporarily_not_requestable'),
                                            modal_title = VuFind.translate('loc_modal_Title_temporarily_not_requestable'),
                                            modal_body  = VuFind.translate('loc_modal_Body_temporarily_not_requestable'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'x',
                                            icon        = 'fa-meh-o',
                                            text        = VuFind.translate('loc_modal_btn_Hover_temporarily_not_requestable'));
              bestOption = loc_modal_button;
              break;
            case 'advance_reservation':
              loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = VuFind.translate('loc_btn_Hover_restricted'),
                                            modal_title = VuFind.translate('loc_modal_Title_restricted'),
                                            modal_body  = VuFind.translate('loc_modal_Body_restricted'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'x',
                                            icon        = 'fa-meh-o',
                                            text        = 'SOV ' + loc_callno);
              bestOption = loc_modal_button;
              break;
            case 'advance_reservation_fr':
              loc_modal_button = create_modal(id = result.id,
                                            loc_code    = loc_abbr,
                                            link_title  = VuFind.translate('loc_btn_Hover_restricted_fr'),
                                            modal_title = VuFind.translate('loc_modal_Title_restricted_fr'),
                                            modal_body  = VuFind.translate('loc_modal_Body_restricted_fr'),
                                            iframe_src  = '',
                                            modal_foot  = '',
                                            icon_class  = 'x',
                                            icon        = 'fa-meh-o',
                                            text        = 'FR ' + loc_callno);
              bestOption = loc_modal_button;
              break;
            case 'false':
              // Remove the "Loading..." - bestoption is and stays empty
              break;
            default:
              loc_button = create_button(href   = VuFind.path + '/Record/'+ result.id +'/Holdings#tabnav',
                                         hover  = VuFind.translate('loc_modal_Title_service_else'),
                                         text   = VuFind.translate('loc_modal_Title_service_else'),
                                         icon   = 'fa-frown-o',
                                         css_classes = 'x');
              loc_modal_link = create_modal(id          = result.id,
                                            loc_code    = 'Undefined',
                                            link_title  = VuFind.translate('infoIcon_Hover'),
                                            modal_title = VuFind.translate('loc_modal_Title_service_else'),
                                            modal_body  = VuFind.translate('loc_modal_Body_service_else'),
                                            iframe_src  = '',
                                            modal_foot  = '');
              fallbackOption = loc_button + ' ' + loc_modal_link;
            }
          $item.find('.status').empty().append(bestOption);
          $item.find('.holdlocation').empty();
          if (fallbackOption) {
              $item.find('.holdlocation').empty().append(fallbackOption);
          }

      // Default case -- load call number and location into appropriate containers:
      $item.find('.callnumber').empty().append(linkCallnumbers(result.callnumber, result.callnumber_handler) + '<br/>');
      $item.find('.location').empty().append(
        result.reserve === 'true'
          ? result.reserve_message
          : result.location
      );
    }
  }

  var ItemStatusHandler = {
    name: "default",
    //array to hold IDs and elements
    itemStatusIds: [], itemStatusEls: [],
    url: '/AJAX/JSON?method=getItemStatuses',
    itemStatusRunning: false,
    dataType: 'json',
    method: 'POST',
    itemStatusTimer: null,
    itemStatusDelay: 200,

    checkItemStatusDone: function checkItemStatusDone(response) {
      var data = response.data;
      for (var j = 0; j < data.statuses.length; j++) {
        var status = data.statuses[j];
        displayItemStatus(status, this.itemStatusEls[status.id]);
        this.itemStatusIds.splice(this.itemStatusIds.indexOf(status.id), 1);
      }
    },
    itemStatusFail: function itemStatusFail(response, textStatus) {
      if (textStatus === 'error' || textStatus === 'abort' || typeof response.responseJSON === 'undefined') {
        return;
      }
      // display the error message on each of the ajax status place holder
      $('.js-item-pending .callnumAndLocation').addClass('text-danger').empty().removeClass('hidden')
        .append(typeof response.responseJSON.data === 'string' ? response.responseJSON.data : VuFind.translate('error_occurred'));
    },
    itemQueueAjax: function itemQueueAjax(id, el) {
      clearTimeout(this.itemStatusTimer);
      this.itemStatusIds.push(id);
      this.itemStatusEls[id] = el;
      this.itemStatusTimer = setTimeout(this.runItemAjaxForQueue.bind(this), this.itemStatusDelay);
      el.addClass('js-item-pending').removeClass('hidden');
      el.find('.callnumAndLocation').removeClass('hidden');
      el.find('.callnumAndLocation .ajax-availability').removeClass('hidden');
      el.find('.status').removeClass('hidden');
    },

    runItemAjaxForQueue: function runItemAjaxForQueue() {
      if (this.itemStatusRunning) {
        this.itemStatusTimer = setTimeout(this.runItemAjaxForQueue.bind(this), this.itemStatusDelay);
        return;
      }
      $.ajax({
        dataType: this.dataType,
        method: this.method,
        url: VuFind.path + this.url,
        context: this,
        data: { 'id': this.itemStatusIds }
      })
        .done(this.checkItemStatusDone)
        .fail( this.itemStatusFail)
        .always(function queueAjaxAlways() {
          this.itemStatusRunning = false;
        });
    }//end runItemAjax
  };

  //add you own overridden handler here
  var OdItemStatusHandler = Object.create(ItemStatusHandler);
  OdItemStatusHandler.url = '/Overdrive/getStatus';
  OdItemStatusHandler.itemStatusDelay = 200;
  OdItemStatusHandler.name = "overdrive";
  OdItemStatusHandler.itemStatusIds = [];
  OdItemStatusHandler.itemStatusEls = [];

  //store the handlers in a "hash" obj
  var checkItemHandlers = {
    'ils': ItemStatusHandler,
    'overdrive': OdItemStatusHandler,
  };

  function checkItemStatus(el) {
    var $item = $(el);
    if ($item.hasClass('js-item-pending') || $item.hasClass('js-item-done')) {
      return;
    }
    if ($item.find('.hiddenId').length === 0) {
      return false;
    }
    var id = $item.find('.hiddenId').val();
    var handlerName = 'ils';
    if ($item.find('.handler-name').length > 0) {
      handlerName = $item.find('.handler-name').val();
    }

    //queue the element into the handler
    checkItemHandlers[handlerName].itemQueueAjax(id, $item);
  }

  function checkItemStatuses(_container) {
    var container = typeof _container === 'undefined'
      ? document.body
      : _container;

    var ajaxItems = $(container).find('.ajaxItem');
    for (var i = 0; i < ajaxItems.length; i++) {
      var id = $(ajaxItems[i]).find('.hiddenId').val();
      var handlerName = 'ils';
      if ($(ajaxItems[i]).find('.handler-name').length > 0) {
        handlerName = $(ajaxItems[i]).find('.handler-name').val();
      }
      if ($(ajaxItems[i]).data("handler-name")) {
        handlerName = $(ajaxItems[i]).data("handler-name");
      }
      checkItemHandlers[handlerName].itemQueueAjax(id, $(ajaxItems[i]));
    }
  }
  function init(_container) {
    if (typeof Hunt === 'undefined') {
      checkItemStatuses(_container);
    } else {
      var container = typeof _container === 'undefined'
        ? document.body
        : _container;
      new Hunt(
        $(container).find('.ajaxItem').toArray(),
        { enter: checkItemStatus }
      );
    }
  }

  return { init: init, check: checkItemStatuses };
});
