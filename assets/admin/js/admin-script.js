(function ($) {
    'use strict';

    $(document).ready(function () {
        var galleryFrame = null;
        var watermarkFrame = null;
        var areaStationLogoFrame = null;
        var socialShareFrame = null;
        var socialShareLogoFrame = null;
        var mapPreviewLeaflet = {
            map: null,
            tileLayer: null,
            layers: [],
            tileReady: false,
            resizeObserver: null,
            centerLat: null,
            centerLng: null,
            zoom: null
        };

        if ($.datepicker) {
            $('.feu-einsatz-datepicker').datepicker({
                dateFormat: 'dd.mm.yy',
                firstDay: 1,
                showButtonPanel: true,
                changeMonth: true,
                changeYear: true,
                showAnim: 'fadeIn',
                duration: 120,
                beforeShow: function () {
                    setTimeout(function () {
                        $('#ui-datepicker-div').addClass('feu-einsatz-datepicker-popup');
                    }, 0);
                }
            });
        }

        function initStreetMemorySuggestions() {
            var records = window.feu_einsatz_ajax && Array.isArray(window.feu_einsatz_ajax.street_suggestion_records)
                ? window.feu_einsatz_ajax.street_suggestion_records
                : [];
            var suggestions = records.length
                ? records
                : (window.feu_einsatz_ajax && Array.isArray(window.feu_einsatz_ajax.street_suggestions)
                    ? window.feu_einsatz_ajax.street_suggestions
                    : []);
            var byStreet = {};
            var renderedStreets = {};
            var normalizeStreet = function (value) {
                return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
            };
            var normalizePostcode = function (value) {
                return String(value || '').replace(/\D+/g, '');
            };
            var normalizeCity = function (value) {
                return String(value || '').replace(/\s+/g, ' ').trim();
            };
            var streetSelector = [
                '#feu_einsatz_strasse',
                '#feu-se-street',
                '#feu-widget-street',
                '[name="feu_einsatz_strasse"]'
            ].join(', ');
            var dropdownClass = 'feu-einsatz-street-search-dropdown';
            var itemClass = 'feu-einsatz-street-search-item';
            var activeClass = 'is-active';
            var hideDropdown = function (input) {
                var dropdown = input && input._feuStreetDropdown ? input._feuStreetDropdown : null;

                if (!dropdown) {
                    return;
                }

                dropdown.hidden = true;
                dropdown.innerHTML = '';
                input._feuStreetItems = [];
                input._feuStreetIndex = -1;
                input.setAttribute('aria-expanded', 'false');
            };
            var ensureDropdown = function (input) {
                var dropdown;
                var parent;

                if (input._feuStreetDropdown) {
                    return input._feuStreetDropdown;
                }

                parent = input.closest('.feu-einsatz-field, .feu-se-field') || input.parentNode;

                if (!parent) {
                    return null;
                }

                parent.classList.add('feu-einsatz-street-search-host');
                dropdown = document.createElement('div');
                dropdown.className = dropdownClass;
                dropdown.hidden = true;
                dropdown.setAttribute('role', 'listbox');
                parent.appendChild(dropdown);
                input._feuStreetDropdown = dropdown;
                input._feuStreetItems = [];
                input._feuStreetIndex = -1;
                input.setAttribute('autocomplete', 'off');
                input.setAttribute('aria-autocomplete', 'list');
                input.setAttribute('aria-expanded', 'false');

                return dropdown;
            };
            var setActiveItem = function (input, index) {
                var items = Array.isArray(input._feuStreetItems) ? input._feuStreetItems : [];

                input._feuStreetIndex = index;
                items.forEach(function (item, itemIndex) {
                    item.classList.toggle(activeClass, itemIndex === index);
                });
            };
            var registerStreetRecord = function (record) {
                var street = String(record && record.street ? record.street : '').trim();
                var key = normalizeStreet(street);
                var postcode = normalizePostcode(record && record.plz ? record.plz : '');
                var city = normalizeCity(record && record.city ? record.city : '');
                var alreadyExists = false;

                if (!street) {
                    return;
                }

                if (!byStreet[key]) {
                    byStreet[key] = [];
                }

                byStreet[key].forEach(function (existingRecord) {
                    if (
                        String(existingRecord.street || '') === street
                        && normalizePostcode(existingRecord.plz || '') === postcode
                        && normalizeCity(existingRecord.city || '') === city
                    ) {
                        alreadyExists = true;
                    }
                });

                if (alreadyExists) {
                    return;
                }

                byStreet[key].push({
                    street: street,
                    plz: postcode,
                    city: city,
                    usage_count: parseInt(record && record.usage_count ? record.usage_count : 0, 10) || 0
                });
            };
            var applyRecordToLocationFields = function (scope, record, options) {
                var $scope = scope ? $(scope) : $(document);
                var $plz = $scope.find('#feu_einsatz_plz, #feu-se-postal-code, #feu-widget-postal-code, [name="feu_einsatz_plz"]').first();
                var $city = $scope.find('#feu_einsatz_stadt, #feu-se-city, #feu-widget-city, [name="feu_einsatz_stadt"]').first();
                var force = options && options.force === true;
                var postcode = normalizePostcode(record && record.plz ? record.plz : '');
                var city = normalizeCity(record && record.city ? record.city : '');

                if ($plz.length && postcode && (force || !String($plz.val() || '').trim())) {
                    $plz.val(postcode).trigger('change');
                }

                if ($city.length && city && (force || !String($city.val() || '').trim())) {
                    $city.val(city).trigger('change');
                }
            };
            var applyLocationFromStreet = function (streetValue, scope, preferredRecord) {
                var key = normalizeStreet(streetValue);
                var matches = byStreet[key] || [];
                var $scope = scope ? $(scope) : $(document);
                var $plz = $scope.find('#feu_einsatz_plz, #feu-se-postal-code, #feu-widget-postal-code, [name="feu_einsatz_plz"]').first();
                var currentPlz;
                var distinctPlz;
                var selected;

                if (!matches.length || !$plz.length) {
                    return;
                }

                if (preferredRecord) {
                    applyRecordToLocationFields($scope, preferredRecord, { force: true });
                    return;
                }

                currentPlz = normalizePostcode($plz.val());
                distinctPlz = matches
                    .map(function (record) {
                        return normalizePostcode(record.plz || '');
                    })
                    .filter(function (value, index, values) {
                        return value && values.indexOf(value) === index;
                    });

                if (!currentPlz && distinctPlz.length > 1) {
                    return;
                }

                selected = matches.find(function (record) {
                    return currentPlz && normalizePostcode(record.plz || '') === currentPlz;
                }) || matches[0];

                applyRecordToLocationFields($scope, selected, { force: false });
            };
            var applySelectedRecord = function (input, record) {
                var form = $(input).closest('form');

                if (!record) {
                    return;
                }

                input.value = String(record.street || '').trim();
                registerStreetRecord(record);
                applyLocationFromStreet(input.value, form, record);
                hideDropdown(input);
                $(input).trigger('change');
            };
            var renderDropdown = function (input, items) {
                var dropdown = ensureDropdown(input);

                if (!dropdown) {
                    return;
                }

                dropdown.innerHTML = '';
                input._feuStreetItems = [];
                input._feuStreetIndex = -1;

                if (!items.length) {
                    hideDropdown(input);
                    return;
                }

                items.forEach(function (record, index) {
                    var button = document.createElement('button');
                    var title = document.createElement('span');
                    var meta = document.createElement('span');
                    var metaParts = [];

                    button.type = 'button';
                    button.className = itemClass;
                    button.setAttribute('role', 'option');
                    button.dataset.street = String(record.street || '');
                    button.dataset.plz = String(record.plz || '');
                    button.dataset.city = String(record.city || '');

                    title.className = itemClass + '-title';
                    title.textContent = String(record.street || '');
                    button.appendChild(title);

                    if (record.plz) {
                        metaParts.push(String(record.plz));
                    }

                    if (record.city) {
                        metaParts.push(String(record.city));
                    }

                    meta.className = itemClass + '-meta';
                    meta.textContent = metaParts.join(' / ');
                    button.appendChild(meta);

                    button.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                    });

                    button.addEventListener('click', function () {
                        applySelectedRecord(input, record);
                    });

                    dropdown.appendChild(button);
                    input._feuStreetItems.push(button);

                    if (index === 0) {
                        setActiveItem(input, 0);
                    }
                });

                dropdown.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            };
            var getLocalMatches = function (query, limit) {
                var normalizedQuery = normalizeStreet(query);
                var matches = [];

                if (!normalizedQuery) {
                    return matches;
                }

                Object.keys(byStreet).forEach(function (streetKey) {
                    var list = byStreet[streetKey] || [];
                    var position = streetKey.indexOf(normalizedQuery);

                    if (!list.length || position === -1) {
                        return;
                    }

                    list.forEach(function (record) {
                        matches.push({
                            street: String(record.street || ''),
                            plz: String(record.plz || ''),
                            city: String(record.city || ''),
                            usage_count: parseInt(record.usage_count || 0, 10) || 0,
                            _match_position: position,
                            _starts_with: position === 0 ? 1 : 0
                        });
                    });
                });

                matches.sort(function (left, right) {
                    if (left._starts_with !== right._starts_with) {
                        return right._starts_with - left._starts_with;
                    }

                    if (left._match_position !== right._match_position) {
                        return left._match_position - right._match_position;
                    }

                    if (left.usage_count !== right.usage_count) {
                        return right.usage_count - left.usage_count;
                    }

                    return String(left.street || '').localeCompare(String(right.street || ''));
                });

                return matches.slice(0, limit).map(function (record) {
                    delete record._match_position;
                    delete record._starts_with;
                    return record;
                });
            };
            var scheduleRemoteSearch = function (input, query) {
                var ajaxConfig = window.feu_einsatz_ajax || {};
                var requestId;

                clearTimeout(input._feuStreetTimer);

                if (!ajaxConfig.ajax_url || !ajaxConfig.nonce || !query) {
                    return;
                }

                input._feuStreetTimer = window.setTimeout(function () {
                    requestId = (input._feuStreetRequestId || 0) + 1;
                    input._feuStreetRequestId = requestId;

                    $.post(ajaxConfig.ajax_url, {
                        action: 'feu_einsatz_search_streets',
                        nonce: ajaxConfig.nonce,
                        query: query,
                        limit: 5
                    }).done(function (response) {
                        var items = response && response.success && response.data && Array.isArray(response.data.items)
                            ? response.data.items
                            : [];

                        if (input._feuStreetRequestId !== requestId) {
                            return;
                        }

                        if (normalizeStreet(input.value) !== normalizeStreet(query)) {
                            return;
                        }

                        items.forEach(function (item) {
                            registerStreetRecord(item);
                        });

                        if (items.length) {
                            renderDropdown(input, items);
                        } else if (!getLocalMatches(query, 5).length) {
                            hideDropdown(input);
                        }
                    });
                }, 140);
            };
            var datalist;

            datalist = document.getElementById('feu-einsatz-street-suggestions');

            if (!datalist && suggestions.length) {
                datalist = document.createElement('datalist');
                datalist.id = 'feu-einsatz-street-suggestions';
                document.body.appendChild(datalist);
            }

            if (datalist) {
                datalist.innerHTML = '';
            }

            suggestions.forEach(function (entry) {
                var record = typeof entry === 'object' && entry !== null ? entry : { street: entry };
                var value = String(record.street || entry || '').trim();
                var key = normalizeStreet(value);
                var option;

                if (!value) {
                    return;
                }

                registerStreetRecord({
                    street: value,
                    plz: String(record.plz || '').replace(/\D+/g, ''),
                    city: String(record.city || '').trim(),
                    usage_count: parseInt(record.usage_count || 0, 10) || 0
                });

                if (!datalist || renderedStreets[key]) {
                    return;
                }

                renderedStreets[key] = true;
                option = document.createElement('option');
                option.value = value;
                if (record.plz || record.city) {
                    option.label = [record.plz, record.city].filter(Boolean).join(' ');
                }
                datalist.appendChild(option);
            });

            $(streetSelector).attr('list', 'feu-einsatz-street-suggestions');
            $(document).on('change blur', streetSelector, function () {
                applyLocationFromStreet(this.value, $(this).closest('form'));
            });
            $(document).on('input', streetSelector, function () {
                var normalized = normalizeStreet(this.value);
                var query = String(this.value || '').trim();
                var localMatches;

                ensureDropdown(this);

                if (!query) {
                    hideDropdown(this);
                    return;
                }

                if (normalized && byStreet[normalized]) {
                    applyLocationFromStreet(this.value, $(this).closest('form'));
                }

                localMatches = getLocalMatches(query, 5);

                if (localMatches.length) {
                    renderDropdown(this, localMatches);
                } else {
                    hideDropdown(this);
                }

                scheduleRemoteSearch(this, query);
            });
            $(document).on('keydown', streetSelector, function (event) {
                var items = Array.isArray(this._feuStreetItems) ? this._feuStreetItems : [];
                var nextIndex;

                if (!items.length) {
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    nextIndex = this._feuStreetIndex < items.length - 1 ? this._feuStreetIndex + 1 : 0;
                    setActiveItem(this, nextIndex);
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    nextIndex = this._feuStreetIndex > 0 ? this._feuStreetIndex - 1 : items.length - 1;
                    setActiveItem(this, nextIndex);
                    return;
                }

                if (event.key === 'Enter' && this._feuStreetIndex >= 0) {
                    event.preventDefault();
                    items[this._feuStreetIndex].click();
                    return;
                }

                if (event.key === 'Escape') {
                    hideDropdown(this);
                }
            });
            $(document).on('focus', streetSelector, function () {
                var query = String(this.value || '').trim();
                var localMatches;

                ensureDropdown(this);

                if (!query) {
                    return;
                }

                localMatches = getLocalMatches(query, 5);

                if (localMatches.length) {
                    renderDropdown(this, localMatches);
                }
            });
            $(document).on('click', function (event) {
                if ($(event.target).closest('.feu-einsatz-street-search-host').length) {
                    return;
                }

                $(streetSelector).each(function () {
                    hideDropdown(this);
                });
            });
        }

        initStreetMemorySuggestions();

        function initPluginSetupStatusPanel() {
            var $panelRoot = $('[data-feu-setup-panel]');

            if (!$panelRoot.length) {
                return;
            }

            function closePanel() {
                $panelRoot.removeClass('is-open');
                $panelRoot.find('.feu-plugin-status-panel').prop('hidden', true);
                $panelRoot.find('[data-feu-setup-panel-toggle]').attr('aria-expanded', 'false');
            }

            function openPanel() {
                $panelRoot.addClass('is-open');
                $panelRoot.find('.feu-plugin-status-panel').prop('hidden', false);
                $panelRoot.find('[data-feu-setup-panel-toggle]').attr('aria-expanded', 'true');
            }

            $(document).on('click', '[data-feu-setup-panel-toggle]', function (event) {
                event.preventDefault();

                if ($panelRoot.hasClass('is-open')) {
                    closePanel();
                } else {
                    openPanel();
                }
            });

            $(document).on('click', '[data-feu-setup-panel-close]', function (event) {
                event.preventDefault();
                closePanel();
            });

            $(document).on('click', function (event) {
                if (!$panelRoot.hasClass('is-open')) {
                    return;
                }

                if ($(event.target).closest('[data-feu-setup-panel]').length) {
                    return;
                }

                closePanel();
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape') {
                    closePanel();
                }
            });
        }

        initPluginSetupStatusPanel();

        $(document).on('input', '#feu_einsatz_datum', function () {
            var el = this;
            var digits = el.value.replace(/\D/g, '').substring(0, 8);
            var formatted = '';

            if (digits.length >= 5) {
                formatted = digits.substring(0, 2) + '.' + digits.substring(2, 4) + '.' + digits.substring(4);
            } else if (digits.length >= 3) {
                formatted = digits.substring(0, 2) + '.' + digits.substring(2);
            } else {
                formatted = digits;
            }

            el.value = formatted;
        });

        function toggleAvailabilityDateFields() {
            var mode = $('input[name="feu_einsatz_availability_mode"]:checked').val();
            var $fields = $('.feu-einsatz-availability-date-fields');

            if (!$fields.length) {
                return;
            }

            $fields.toggle(mode === 'date');
        }

        function syncAvailabilityPreview() {
            function parseGermanDateTime(dateValue, timeValue) {
                var match = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(dateValue);
                var timeMatch = /^(\d{2}):(\d{2})$/.exec(timeValue || '08:00');

                if (!match || !timeMatch) {
                    return null;
                }

                return new Date(
                    Number(match[3]),
                    Number(match[2]) - 1,
                    Number(match[1]),
                    Number(timeMatch[1]),
                    Number(timeMatch[2]),
                    0,
                    0
                );
            }

            function formatGermanDate(dateObject) {
                return String(dateObject.getDate()).padStart(2, '0')
                    + '.'
                    + String(dateObject.getMonth() + 1).padStart(2, '0')
                    + '.'
                    + String(dateObject.getFullYear());
            }

            function formatGermanTime(dateObject) {
                return String(dateObject.getHours()).padStart(2, '0')
                    + ':'
                    + String(dateObject.getMinutes()).padStart(2, '0');
            }

            var $datePreview = $('#feu-einsatz-availability-preview-date');
            var $timePreview = $('#feu-einsatz-availability-preview-time');
            var mode = $('input[name="feu_einsatz_availability_mode"]:checked').val() || 'date';

            if (!$datePreview.length || !$timePreview.length) {
                return;
            }

            var dateValue = $.trim($('#feu_einsatz_datum').val() || '');
            var timeValue = $.trim($('#feu_einsatz_uhrzeit').val() || '');

            if (mode === 'plus2') {
                var eventDate = parseGermanDateTime(dateValue, timeValue);

                if (eventDate instanceof Date && !isNaN(eventDate.getTime())) {
                    eventDate.setHours(eventDate.getHours() + 48);
                    $datePreview.text(formatGermanDate(eventDate));
                    $timePreview.text(formatGermanTime(eventDate));
                    return;
                }
            }

            $datePreview.text(dateValue || 'Nicht gesetzt');
            $timePreview.text(timeValue || '08:00');
        }

        $(document).on('change', 'input[name="feu_einsatz_availability_mode"]', toggleAvailabilityDateFields);
        $(document).on('input change', '#feu_einsatz_datum, #feu_einsatz_uhrzeit', syncAvailabilityPreview);
        toggleAvailabilityDateFields();
        syncAvailabilityPreview();

        $('#feu_einsatz_plz').on('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        $('.feu-einsatz-delete-participant, .feu-einsatz-delete-organization').on('click', function (e) {
            if (!confirm(feu_einsatz_ajax.strings.confirm_delete)) {
                e.preventDefault();
                return false;
            }
        });

        $('#feu-einsatz-search-participant').on('keyup', function () {
            var search = $(this).val().toLowerCase();
            $('.feu-einsatz-participant-list tbody tr').each(function () {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(search) > -1);
            });
        });

        $(document).on('click', '#feu-einsatz-generate-map-image', function (event) {
            event.preventDefault();
            generateMapImage($(this));
        });

        $(document).on('click', '#feu-einsatz-regenerate-map-image', function (event) {
            event.preventDefault();
            if (confirm('Moechten Sie das Kartenbild wirklich neu generieren? Das alte Bild wird ersetzt.')) {
                generateMapImage($(this));
            }
        });

        $(document).on('click', '.feu-einsatz-delete-map-image-button', function (event) {
            var $button = $(this);
            var postId = String($button.data('post-id') || '');
            var nonce = String($button.data('nonce') || '');
            var redirectTo = String($button.data('redirect-to') || window.location.href);
            var confirmMessage = String($button.data('confirm') || 'Soll das automatisch generierte Kartenbild wirklich geloescht werden?');
            var $form;

            event.preventDefault();

            if (!postId || !nonce) {
                return;
            }

            if (!confirm(confirmMessage)) {
                return;
            }

            $form = $('<form>', {
                method: 'post',
                action: window.feu_einsatz_ajax && window.feu_einsatz_ajax.admin_post_url
                    ? window.feu_einsatz_ajax.admin_post_url
                    : 'admin-post.php'
            });

            $('<input>', { type: 'hidden', name: 'action', value: 'feu_einsatz_delete_map_image' }).appendTo($form);
            $('<input>', { type: 'hidden', name: 'post_id', value: postId }).appendTo($form);
            $('<input>', { type: 'hidden', name: 'redirect_to', value: redirectTo }).appendTo($form);
            $('<input>', { type: 'hidden', name: '_wpnonce', value: nonce }).appendTo($form);

            $form.appendTo(document.body).trigger('submit');
        });

        $(document).on('click', '#feu-einsatz-add-gallery-images', function (event) {
            var mediaApi = getMediaApi();

            event.preventDefault();

            if (!mediaApi) {
                alert('Die WordPress Mediathek ist nicht verfuegbar.');
                return;
            }

            if (!galleryFrame) {
                galleryFrame = mediaApi({
                    title: 'Fotos fuer Einsatzbericht auswaehlen',
                    button: {
                        text: 'Fotos uebernehmen'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: true
                });

                galleryFrame.on('select', function () {
                    var selection = galleryFrame.state().get('selection').toJSON();
                    var existingIds = getGalleryIds();

                    selection.forEach(function (attachment) {
                        var attachmentId = String(attachment.id);

                        if (existingIds.indexOf(attachmentId) === -1) {
                            existingIds.push(attachmentId);
                            appendGalleryItem(attachment.id, attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                        }
                    });

                    setGalleryIds(existingIds);
                });
            }

            galleryFrame.open();
        });

        $(document).on('click', '.feu-einsatz-remove-gallery-image', function () {
            var $item = $(this).closest('.feu-einsatz-gallery-item');
            var attachmentId = String($item.data('id'));
            var galleryIds = getGalleryIds().filter(function (value) {
                return value !== attachmentId;
            });

            setGalleryIds(galleryIds);
            $item.remove();
        });

        $(document).on('click', '#feu-einsatz-watermark-image-select', function (event) {
            var mediaApi = getMediaApi();

            event.preventDefault();

            if (!mediaApi) {
                alert('Die WordPress Mediathek ist nicht verfuegbar.');
                return;
            }

            if (!watermarkFrame) {
                watermarkFrame = mediaApi({
                    title: 'Wasserzeichen-Bild auswaehlen',
                    button: {
                        text: 'Bild verwenden'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });

                watermarkFrame.on('select', function () {
                    var selection = watermarkFrame.state().get('selection').first();

                    if (!selection) {
                        return;
                    }

                    var attachment = selection.toJSON();
                    var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

                    $('#feu_einsatz_photo_watermark_image_id').val(attachment.id);
                    $('#feu-einsatz-watermark-image-preview').html(
                        '<img src="' + previewUrl + '" alt="" class="feu-einsatz-watermark-preview-image" />'
                    );
                    $('#feu-einsatz-watermark-image-remove').prop('hidden', false);
                });
            }

            watermarkFrame.open();
        });

        $(document).on('click', '#feu-einsatz-watermark-image-remove', function (event) {
            event.preventDefault();
            $('#feu_einsatz_photo_watermark_image_id').val('0');
            $('#feu-einsatz-watermark-image-preview').html('<div class="feu-einsatz-watermark-placeholder">Noch kein Wasserzeichen-Bild ausgewaehlt.</div>');
            $(this).prop('hidden', true);
        });

        $(document).on('click', '#feu-einsatz-area-station-logo-select', function (event) {
            var mediaApi = getMediaApi();

            event.preventDefault();

            if (!mediaApi) {
                alert('Die WordPress Mediathek ist nicht verfuegbar.');
                return;
            }

            if (!areaStationLogoFrame) {
                areaStationLogoFrame = mediaApi({
                    title: 'Feuerwehr-Logo auswaehlen',
                    button: {
                        text: 'Logo verwenden'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });

                areaStationLogoFrame.on('select', function () {
                    var selection = areaStationLogoFrame.state().get('selection').first();

                    if (!selection) {
                        return;
                    }

                    var attachment = selection.toJSON();
                    var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

                    $('#feu_einsatz_area_station_logo_id').val(attachment.id);
                    $('#feu-einsatz-area-station-logo-preview').html(
                        '<img src="' + previewUrl + '" alt="" class="feu-einsatz-watermark-preview-image" />'
                    );
                    $('#feu-einsatz-area-station-logo-remove').prop('hidden', false);
                    updateMapPreviewLivePreview();
                });
            }

            areaStationLogoFrame.open();
        });

        $(document).on('click', '#feu-einsatz-area-station-logo-remove', function (event) {
            event.preventDefault();
            $('#feu_einsatz_area_station_logo_id').val('0');
            $('#feu-einsatz-area-station-logo-preview').html('<div class="feu-einsatz-watermark-placeholder">Noch kein Feuerwehr-Logo ausgewaehlt.</div>');
            $(this).prop('hidden', true);
            updateMapPreviewLivePreview();
        });

        $(document).on('click', '#feu-einsatz-social-share-image-select', function (event) {
            var mediaApi = getMediaApi();

            event.preventDefault();

            if (!mediaApi) {
                alert('Die WordPress Mediathek ist nicht verfuegbar.');
                return;
            }

            if (!socialShareFrame) {
                socialShareFrame = mediaApi({
                    title: 'Hintergrundbild fuer Share-Karte auswaehlen',
                    button: {
                        text: 'Bild verwenden'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });

                socialShareFrame.on('select', function () {
                    var selection = socialShareFrame.state().get('selection').first();

                    if (!selection) {
                        return;
                    }

                    var attachment = selection.toJSON();
                    var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

                    $('#feu_einsatz_social_share_background_id').val(attachment.id);
                    $('#feu-einsatz-social-share-image-preview').html(
                        '<img src="' + previewUrl + '" alt="" class="feu-einsatz-watermark-preview-image" />'
                    );
                    $('#feu-einsatz-social-share-image-remove').prop('hidden', false);
                    updateSocialShareLivePreview();
                });
            }

            socialShareFrame.open();
        });

        $(document).on('click', '#feu-einsatz-social-share-image-remove', function (event) {
            event.preventDefault();
            $('#feu_einsatz_social_share_background_id').val('0');
            $('#feu-einsatz-social-share-image-preview').html('<div class="feu-einsatz-watermark-placeholder">Noch kein Hintergrundbild ausgewaehlt. Ohne eigenes Bild nutzt die Share-Karte automatisch das vorhandene Beitragsbild oder eine neutrale Flaeche.</div>');
            $(this).prop('hidden', true);
            updateSocialShareLivePreview();
        });

        $(document).on('change', '#feu_einsatz_social_share_image_mode, [data-feu-share-preview-field-toggle]', updateSocialShareLivePreview);
        $(document).on('click', '#feu-einsatz-social-share-logo-select', function (event) {
            var mediaApi = getMediaApi();

            event.preventDefault();

            if (!mediaApi) {
                alert('Die WordPress Mediathek ist nicht verfuegbar.');
                return;
            }

            if (!socialShareLogoFrame) {
                socialShareLogoFrame = mediaApi({
                    title: 'Logo fuer Share-Karte auswaehlen',
                    button: {
                        text: 'Logo verwenden'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });

                socialShareLogoFrame.on('select', function () {
                    var selection = socialShareLogoFrame.state().get('selection').first();

                    if (!selection) {
                        return;
                    }

                    var attachment = selection.toJSON();
                    var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

                    $('#feu_einsatz_social_share_logo_id').val(attachment.id);
                    $('#feu-einsatz-social-share-logo-preview').html(
                        '<img src="' + previewUrl + '" alt="" class="feu-einsatz-watermark-preview-image" />'
                    );
                    $('#feu-einsatz-social-share-logo-remove').prop('hidden', false);
                    updateSocialShareLivePreview();
                });
            }

            socialShareLogoFrame.open();
        });

        $(document).on('click', '#feu-einsatz-social-share-logo-remove', function (event) {
            event.preventDefault();
            $('#feu_einsatz_social_share_logo_id').val('0');
            $('#feu-einsatz-social-share-logo-preview').html('<div class="feu-einsatz-watermark-placeholder">Noch kein Share-Logo ausgewaehlt. Ohne eigenes Logo bleibt die Fussleiste der Share-Karte frei.</div>');
            $(this).prop('hidden', true);
            updateSocialShareLivePreview();
        });

        $(document).on(
            'change input',
            '#feu_einsatz_social_share_layout, #feu_einsatz_social_share_badge_text, #feu_einsatz_social_share_cta_text, #feu_einsatz_social_share_title_color, #feu_einsatz_social_share_description_color, #feu_einsatz_social_share_panel_color, #feu_einsatz_social_share_accent_color, #feu_einsatz_social_share_title_scale, #feu_einsatz_social_share_description_scale, #feu_einsatz_social_share_description_max_lines, #feu_einsatz_social_share_logo_width, #feu_einsatz_social_share_overlay_enabled, #feu_einsatz_social_share_image_blur, #feu_einsatz_social_share_panel_radius, #feu_einsatz_social_share_badge_radius, #feu_einsatz_social_share_link_radius, #feu_einsatz_social_share_text_align, #feu_einsatz_social_share_logo_position',
            updateSocialShareLivePreview
        );

        function getSharePreviewTitleAutoScale(titleText, layout) {
            var length = $.trim(titleText || '').length;

            if (layout === 'story' || layout === 'feed') {
                if (length > 56) { return 72; }
                if (length > 44) { return 80; }
                if (length > 34) { return 88; }
                if (length > 26) { return 94; }
                return 100;
            }

            if (length > 52) { return 76; }
            if (length > 40) { return 84; }
            if (length > 30) { return 92; }
            return 100;
        }

        function updateSocialShareLivePreview() {
            var $root = $('[data-feu-share-preview-root]');

            if (!$root.length) {
                return;
            }

            var mode = $('input[name="feu_einsatz_social_share_image_mode"]:checked').val() || 'post_image';
            var layout = $('#feu_einsatz_social_share_layout').val() || 'wide';
            var badgeText = $.trim($('#feu_einsatz_social_share_badge_text').val() || '') || 'EINSATZBERICHT';
            var titleColor = $('#feu_einsatz_social_share_title_color').val() || '#ffffff';
            var descriptionColor = $('#feu_einsatz_social_share_description_color').val() || '#dbeafe';
            var panelColor = $('#feu_einsatz_social_share_panel_color').val() || '#0f2f5f';
            var accentColor = $('#feu_einsatz_social_share_accent_color').val() || '#ef233c';
            var titleScale = parseInt($('#feu_einsatz_social_share_title_scale').val() || '118', 10);
            var descriptionScale = parseInt($('#feu_einsatz_social_share_description_scale').val() || '112', 10);
            var descriptionLines = parseInt($('#feu_einsatz_social_share_description_max_lines').val() || '5', 10);
            var logoWidth = parseInt($('#feu_einsatz_social_share_logo_width').val() || '220', 10);
            var overlayEnabled = $('#feu_einsatz_social_share_overlay_enabled').is(':checked');
            var imageBlur = parseInt($('#feu_einsatz_social_share_image_blur').val() || '0', 10);
            var panelRadius = parseInt($('#feu_einsatz_social_share_panel_radius').val() || '30', 10);
            var badgeRadius = parseInt($('#feu_einsatz_social_share_badge_radius').val() || '40', 10);
            var linkRadius = parseInt($('#feu_einsatz_social_share_link_radius').val() || '14', 10);
            var textAlign = $('#feu_einsatz_social_share_text_align').val() || 'auto';
            var logoPosition = $('#feu_einsatz_social_share_logo_position').val() || 'bottom-right';
            var previewImageUrl = $('#feu-einsatz-social-share-image-preview img').first().attr('src') || '';
            var logoImageUrl = $('#feu-einsatz-social-share-logo-preview img').first().attr('src') || '';
            var $card = $root.find('[data-feu-share-preview-card]');
            var $modeLabel = $root.find('[data-feu-share-preview-mode-label]');
            var $note = $root.find('[data-feu-share-preview-note]');
            var $badge = $root.find('[data-feu-share-preview-badge]');
            var $logoWrap = $root.find('.feu-einsatz-social-share-live-preview-logo');
            var $logo = $root.find('[data-feu-share-preview-logo]');
            var $title = $root.find('.feu-einsatz-social-share-live-preview-title');
            var selectedFields = {};

            $('[data-feu-share-preview-field-toggle]').each(function () {
                selectedFields[String($(this).val() || '')] = $(this).is(':checked');
            });

            titleScale = Math.max(70, Math.min(180, isNaN(titleScale) ? 118 : titleScale));
            descriptionScale = Math.max(70, Math.min(180, isNaN(descriptionScale) ? 112 : descriptionScale));
            descriptionLines = Math.max(2, Math.min(10, isNaN(descriptionLines) ? 5 : descriptionLines));
            logoWidth = Math.max(60, Math.min(520, isNaN(logoWidth) ? 220 : logoWidth));
            imageBlur = Math.max(0, Math.min(20, isNaN(imageBlur) ? 0 : imageBlur));
            panelRadius = Math.max(0, Math.min(120, isNaN(panelRadius) ? 30 : panelRadius));
            badgeRadius = Math.max(0, Math.min(120, isNaN(badgeRadius) ? 40 : badgeRadius));
            linkRadius = Math.max(0, Math.min(80, isNaN(linkRadius) ? 14 : linkRadius));

            $card.toggleClass('is-generated-mode', mode === 'generated');
            $card.toggleClass('is-post-image-mode', mode !== 'generated');
            $card.removeClass('is-layout-wide is-layout-story is-layout-feed is-logo-position-bottom-right is-logo-position-bottom-left is-logo-position-top-right is-logo-position-top-left is-logo-position-hidden is-text-align-auto is-text-align-left is-text-align-center is-text-align-right')
                .addClass('is-layout-' + layout)
                .addClass('is-logo-position-' + logoPosition)
                .addClass('is-text-align-' + textAlign)
                .toggleClass('is-overlay-disabled', !overlayEnabled);
            $card.attr('data-feu-share-preview-layout', layout);
            $card.css('--feu-share-preview-panel', panelColor);
            $card.css('--feu-share-preview-accent', accentColor);
            $card.css('--feu-share-preview-title', titleColor);
            $card.css('--feu-share-preview-description', descriptionColor);
            $card.css('--feu-share-preview-title-scale', Math.round(titleScale * (getSharePreviewTitleAutoScale($title.text(), layout) / 100)) + '%');
            $card.css('--feu-share-preview-description-scale', descriptionScale + '%');
            $card.css('--feu-share-preview-description-lines', descriptionLines);
            $card.css('--feu-share-preview-logo-width', logoWidth + 'px');
            $card.css('--feu-share-preview-image-blur', imageBlur + 'px');
            $card.css('--feu-share-preview-panel-radius', panelRadius + 'px');
            $card.css('--feu-share-preview-badge-radius', badgeRadius + 'px');
            $card.css('--feu-share-preview-link-radius', linkRadius + 'px');

            $('[data-feu-share-title-scale-value]').text(titleScale);
            $('[data-feu-share-description-scale-value]').text(descriptionScale);
            $('[data-feu-share-description-lines-value]').text(descriptionLines);
            $('[data-feu-share-logo-width-value]').text(logoWidth);
            $('[data-feu-share-image-blur-value]').text(imageBlur);
            $('[data-feu-share-panel-radius-value]').text(panelRadius);
            $('[data-feu-share-badge-radius-value]').text(badgeRadius);
            $('[data-feu-share-link-radius-value]').text(linkRadius);

            if (previewImageUrl) {
                $card.css('background-image', "url('" + previewImageUrl.replace(/'/g, "\\'") + "')");
                $card.css('--feu-share-preview-bg-image', "url('" + previewImageUrl.replace(/'/g, "\\'") + "')");
            } else {
                $card.css('background-image', '');
                $card.css('--feu-share-preview-bg-image', 'none');
            }

            if ($badge.length) {
                $badge.text(badgeText);
            }

            if (logoImageUrl) {
                $logo.attr('src', logoImageUrl);
                $logoWrap.prop('hidden', false);
            } else {
                $logo.attr('src', '');
                $logoWrap.prop('hidden', true);
            }

            if ($modeLabel.length) {
                $modeLabel.text(
                    mode === 'generated'
                        ? 'Aktiv: Generierte Share-Karte'
                        : 'Aktiv: Vorhandenes Beitragsbild'
                );
            }

            if ($note.length) {
                $note.text(
                    mode === 'generated'
                        ? 'Die Vorschau zeigt die aktuelle Komposition der generierten Share-Karte.'
                        : 'Aktuell ist das vorhandene Beitragsbild aktiv.'
                );
            }

            $root.find('[data-feu-share-preview-field]').each(function () {
                var fieldKey = String($(this).data('feuSharePreviewField') || $(this).attr('data-feu-share-preview-field') || '');
                this.hidden = !selectedFields[fieldKey];
            });

            $root.find('[data-feu-share-preview-group]').each(function () {
                var hasVisibleChildren = $(this).find('[data-feu-share-preview-field]').filter(function () {
                    return !this.hidden;
                }).length > 0;
                this.hidden = !hasVisibleChildren;
            });
        }

        function updateShareSettingsStatus(message) {
            var $status = $('[data-feu-share-settings-status]');

            if (!$status.length) {
                return;
            }

            $status.text(message || 'Vorschau ist aktuell. Aenderungen bitte unten speichern.');
        }

        $(document).on('click', '[data-feu-share-preset]', function () {
            var preset = String($(this).data('feuSharePreset') || 'operational');
            var presets = {
                operational: {
                    mode: 'generated', layout: 'wide', overlay: true, blur: 2,
                    panel: '#0f2f5f', accent: '#ef233c', title: '#ffffff', text: '#dbeafe', align: 'left'
                },
                photo: {
                    mode: 'post_image', layout: 'wide', overlay: true, blur: 0,
                    panel: '#0f2f5f', accent: '#ef233c', title: '#ffffff', text: '#dbeafe', align: 'left'
                },
                story: {
                    mode: 'generated', layout: 'story', overlay: true, blur: 4,
                    panel: '#0f2f5f', accent: '#ef233c', title: '#ffffff', text: '#dbeafe', align: 'center'
                }
            };
            var selected = presets[preset];

            if (!selected) {
                return;
            }

            $('input[name="feu_einsatz_social_share_image_mode"][value="' + selected.mode + '"]').prop('checked', true);
            $('#feu_einsatz_social_share_layout').val(selected.layout);
            $('#feu_einsatz_social_share_overlay_enabled').prop('checked', selected.overlay);
            $('#feu_einsatz_social_share_image_blur').val(selected.blur);
            $('#feu_einsatz_social_share_panel_color').val(selected.panel);
            $('#feu_einsatz_social_share_accent_color').val(selected.accent);
            $('#feu_einsatz_social_share_title_color').val(selected.title);
            $('#feu_einsatz_social_share_description_color').val(selected.text);
            $('#feu_einsatz_social_share_text_align').val(selected.align);
            $('[data-feu-share-preset]').removeClass('is-active');
            $(this).addClass('is-active');
            updateSocialShareLivePreview();
            updateShareSettingsStatus('Preset angewendet. Bitte Einstellungen speichern.');
        });

        $(document).on('input change', '#tab-sozial input, #tab-sozial select', function () {
            updateShareSettingsStatus('Ungespeicherte Aenderungen – Vorschau wurde aktualisiert.');
        });

        updateSocialShareLivePreview();
        updateShareSettingsStatus();

        $(document).on(
            'input change',
            '#feu_einsatz_map_preview_heading_text, #feu_einsatz_map_preview_show_panel, #feu_einsatz_map_preview_show_panel_heading, #feu_einsatz_map_preview_show_panel_address, #feu_einsatz_map_preview_panel_position, #feu_einsatz_map_preview_street_label_prefix, #feu_einsatz_map_preview_street_label_position, #feu_einsatz_map_preview_show_attribution, #feu_einsatz_map_preview_attribution_text, #feu_einsatz_map_preview_attribution_position, #feu_einsatz_map_preview_highlight_color, #feu_einsatz_map_preview_stroke_width, #feu_einsatz_map_preview_font_family, #feu_einsatz_map_preview_show_street_label, #feu_einsatz_area_station_street, #feu_einsatz_area_station_postcode, #feu_einsatz_area_station_city, #feu_einsatz_area_station_logo_size, #feu_einsatz_photo_watermark_text, #feu_einsatz_map_zoom, #feu_einsatz_map_height',
            updateMapPreviewLivePreview
        );

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function clampNumber(value, min, max) {
            return Math.min(max, Math.max(min, value));
        }

        function normalizeMapPreviewPoint(point) {
            var lat;
            var lng;

            if (Array.isArray(point) && point.length >= 2) {
                lat = parseFloat(point[0]);
                lng = parseFloat(point[1]);
            } else if (point && typeof point === 'object') {
                lat = parseFloat(point.lat !== undefined ? point.lat : point.latitude);
                lng = parseFloat(point.lng !== undefined ? point.lng : (point.lon !== undefined ? point.lon : point.longitude));
            }

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return null;
            }

            return [lat, lng];
        }

        function normalizeMapPreviewGeometry(geometry) {
            var normalized = [];

            if (!Array.isArray(geometry)) {
                return normalized;
            }

            geometry.forEach(function (segment, segmentIndex) {
                var rawPoints = segment && Array.isArray(segment.points) ? segment.points : segment;
                var points = [];
                var kind = segmentIndex === 0 ? 'road' : String(segment && segment.kind || 'road');

                if (!Array.isArray(rawPoints)) {
                    return;
                }

                rawPoints.forEach(function (point) {
                    var normalizedPoint = normalizeMapPreviewPoint(point);

                    if (normalizedPoint) {
                        points.push(normalizedPoint);
                    }
                });

                if (points.length >= 2) {
                    normalized.push({
                        kind: kind === 'pedestrian' ? 'pedestrian' : 'road',
                        points: points
                    });
                }
            });

            return normalized;
        }

        function normalizeMapPreviewAddressPart(value) {
            return String(value || '')
                .trim()
                .replace(/\s+/g, ' ')
                .toLowerCase();
        }

        function buildMapPreviewFallbackGeometry(centerLat, centerLng) {
            centerLat = parseFloat(centerLat);
            centerLng = parseFloat(centerLng);

            if (!Number.isFinite(centerLat) || !Number.isFinite(centerLng)) {
                centerLat = 53.5853;
                centerLng = 9.8827;
            }

            return [{
                kind: 'road',
                points: [
                    [centerLat - 0.00042, centerLng - 0.00115],
                    [centerLat - 0.00014, centerLng - 0.00038],
                    [centerLat + 0.00018, centerLng + 0.00042],
                    [centerLat + 0.00044, centerLng + 0.00116]
                ]
            }];
        }

        function readMapPreviewGeometry($stage, stationStreet, stationPostcode, stationCity) {
            var savedStreet = normalizeMapPreviewAddressPart($stage.attr('data-feu-map-preview-street'));
            var savedPostcode = normalizeMapPreviewAddressPart($stage.attr('data-feu-map-preview-postcode'));
            var savedCity = normalizeMapPreviewAddressPart($stage.attr('data-feu-map-preview-city') || 'Hamburg');
            var currentStreet = normalizeMapPreviewAddressPart(stationStreet);
            var currentPostcode = normalizeMapPreviewAddressPart(stationPostcode);
            var currentCity = normalizeMapPreviewAddressPart(stationCity || 'Hamburg');
            var rawGeometry;
            var parsedGeometry;

            if (
                currentStreet !== savedStreet
                || currentPostcode !== savedPostcode
                || currentCity !== savedCity
            ) {
                return [];
            }

            rawGeometry = $stage.attr('data-feu-map-preview-geometry') || '[]';

            try {
                parsedGeometry = JSON.parse(rawGeometry);
            } catch (error) {
                parsedGeometry = [];
            }

            return normalizeMapPreviewGeometry(parsedGeometry);
        }

        function buildMapPreviewFallbackMarkup(options) {
            var width = 1200;
            var height = clampNumber(parseInt(options.height || 400, 10) || 400, 320, 680);
            var lineColor = options.color || '#d92d20';
            var lineWidth = clampNumber(parseInt(options.strokeWidth || 8, 10) || 8, 3, 18);
            var heading = String(options.heading || 'Einsatzort');
            var address = String(options.address || '');
            var labelText = String(options.labelText || '');
            var showLabel = !!options.showLabel;
            var geometry = Array.isArray(options.geometry) ? options.geometry : [];
            var marker = Array.isArray(options.marker) ? options.marker : null;
            var normalizedSegments = [];
            var mainSegment = null;
            var points = [];
            var minLat;
            var maxLat;
            var minLng;
            var maxLng;
            var latRange;
            var lngRange;
            var padLat;
            var padLng;
            var gridLines = [];
            var contextPaths = [];
            var mainPath = [];
            var markerMarkup = '';
            var badgeMarkup = '';
            var panelWidth = clampNumber(Math.round(width * 0.34), 320, 420);
            var markerPoint = null;
            var labelPoint = null;
            var i;

            geometry.forEach(function (segment, segmentIndex) {
                var segmentPoints = [];
                var segmentKind = segmentIndex === 0 ? 'road' : String(segment && segment.kind || 'road');

                if (!segment || !Array.isArray(segment.points)) {
                    return;
                }

                segment.points.forEach(function (point) {
                    if (Array.isArray(point) && point.length >= 2 && Number.isFinite(point[0]) && Number.isFinite(point[1])) {
                        segmentPoints.push({ lat: point[0], lng: point[1] });
                        points.push({ lat: point[0], lng: point[1] });
                    }
                });

                if (segmentPoints.length >= 2) {
                    normalizedSegments.push({
                        kind: segmentKind === 'pedestrian' ? 'pedestrian' : 'road',
                        points: segmentPoints
                    });
                }
            });

            if (marker && marker.length >= 2 && Number.isFinite(marker[0]) && Number.isFinite(marker[1])) {
                points.push({ lat: marker[0], lng: marker[1] });
            }

            if (!points.length) {
                return '';
            }

            mainSegment = normalizedSegments.length ? normalizedSegments[0] : null;

            if (!mainSegment) {
                return '';
            }

            minLat = Math.min.apply(null, points.map(function (point) { return point.lat; }));
            maxLat = Math.max.apply(null, points.map(function (point) { return point.lat; }));
            minLng = Math.min.apply(null, points.map(function (point) { return point.lng; }));
            maxLng = Math.max.apply(null, points.map(function (point) { return point.lng; }));
            latRange = Math.max(maxLat - minLat, 0.008);
            lngRange = Math.max(maxLng - minLng, 0.008);
            padLat = Math.max(0.0025, latRange * 0.16);
            padLng = Math.max(0.0025, lngRange * 0.16);
            minLat -= padLat;
            maxLat += padLat;
            minLng -= padLng;
            maxLng += padLng;
            latRange = Math.max(maxLat - minLat, 0.008);
            lngRange = Math.max(maxLng - minLng, 0.008);

            function projectPoint(point) {
                return {
                    x: 76 + (((point.lng - minLng) / lngRange) * (width - 152)),
                    y: 76 + (((maxLat - point.lat) / latRange) * (height - 152))
                };
            }

            for (i = 1; i <= 5; i += 1) {
                gridLines.push('<line x1="' + (((width / 6) * i).toFixed(2)) + '" y1="0" x2="' + (((width / 6) * i).toFixed(2)) + '" y2="' + height + '" stroke="#0f172a" stroke-opacity="0.04" stroke-width="1" />');
            }

            for (i = 1; i <= 4; i += 1) {
                gridLines.push('<line x1="0" y1="' + (((height / 5) * i).toFixed(2)) + '" x2="' + width + '" y2="' + (((height / 5) * i).toFixed(2)) + '" stroke="#0f172a" stroke-opacity="0.04" stroke-width="1" />');
            }

            normalizedSegments.slice(1).forEach(function (segment) {
                var commands = [];

                segment.points.forEach(function (point, index) {
                    var projected = projectPoint(point);
                    commands.push((index === 0 ? 'M' : 'L') + projected.x.toFixed(2) + ' ' + projected.y.toFixed(2));
                });

                if (commands.length >= 2) {
                    contextPaths.push({
                        d: commands.join(' '),
                        halo: segment.kind === 'pedestrian' ? 6 : 8,
                        stroke: segment.kind === 'pedestrian' ? 3 : 4,
                        dash: segment.kind === 'pedestrian' ? '18 12' : ''
                    });
                }
            });

            mainSegment.points.forEach(function (point, index) {
                var projected = projectPoint(point);
                mainPath.push((index === 0 ? 'M' : 'L') + projected.x.toFixed(2) + ' ' + projected.y.toFixed(2));
            });

            if (marker && marker.length >= 2) {
                markerPoint = projectPoint({ lat: marker[0], lng: marker[1] });
                markerMarkup =
                    '<circle cx="' + markerPoint.x.toFixed(2) + '" cy="' + markerPoint.y.toFixed(2) + '" r="15" fill="#ffffff" fill-opacity="0.94" stroke="#0f172a" stroke-opacity="0.16" stroke-width="2" />' +
                    '<circle cx="' + markerPoint.x.toFixed(2) + '" cy="' + markerPoint.y.toFixed(2) + '" r="7" fill="' + escapeHtml(lineColor) + '" />';
            }

            labelPoint = mainSegment.points.length
                ? projectPoint({
                    lat: mainSegment.points[Math.floor(mainSegment.points.length / 2)].lat,
                    lng: mainSegment.points[Math.floor(mainSegment.points.length / 2)].lng
                })
                : (markerPoint || { x: width * 0.56, y: height * 0.45 });

            if (showLabel && labelText) {
                var badgeClass = 'feu-einsatz-map-inline-preview-badge';
                var badgeStyle = '';

                if (options.streetLabelPosition && options.streetLabelPosition !== 'auto') {
                    badgeClass += ' is-position-' + options.streetLabelPosition;
                } else {
                    badgeStyle = ' style="left:' + clampNumber(labelPoint.x + 20, 24, width - 320).toFixed(2) + 'px;top:' + clampNumber(labelPoint.y - 68, 24, height - 124).toFixed(2) + 'px;"';
                }

                badgeMarkup =
                    '<div class="' + badgeClass + '"' + badgeStyle + '>' +
                        escapeHtml(labelText) +
                    '</div>';
            }

            var panelMarkup = '';
            var attributionMarkup = '';

            if (options.showPanel && (options.showPanelHeading || options.showPanelAddress)) {
                panelMarkup =
                    '<div class="feu-einsatz-map-inline-preview-panel is-position-' + escapeHtml(options.panelPosition || 'bottom-left') + '" style="width:' + panelWidth + 'px;">' +
                        (options.showPanelHeading ? '<strong>' + escapeHtml(heading) + '</strong>' : '') +
                        (options.showPanelAddress ? '<span>' + escapeHtml(address) + '</span>' : '') +
                    '</div>';
            }

            if (options.showAttribution) {
                attributionMarkup =
                    '<div class="feu-einsatz-map-inline-preview-attribution is-position-' + escapeHtml(options.attributionPosition || 'bottom-right') + '">' +
                        escapeHtml(options.attributionText || 'Leaflet | \u00a9 OpenStreetMap contributors') +
                    '</div>';
            }

            return '' +
                '<div class="feu-einsatz-map-inline-preview" style="height:' + height + 'px;">' +
                    '<svg viewBox="0 0 ' + width + ' ' + height + '" role="img" preserveAspectRatio="none">' +
                        '<rect x="0" y="0" width="' + width + '" height="' + height + '" fill="#f7fbff" />' +
                        gridLines.join('') +
                        contextPaths.map(function (path) {
                            var dashAttr = path.dash ? ' stroke-dasharray="' + escapeHtml(path.dash) + '"' : '';
                            return '' +
                                '<path d="' + escapeHtml(path.d) + '" fill="none" stroke="#ffffff" stroke-width="' + path.halo + '" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.86"' + dashAttr + ' />' +
                                '<path d="' + escapeHtml(path.d) + '" fill="none" stroke="#d5e0ec" stroke-width="' + path.stroke + '" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.96"' + dashAttr + ' />';
                        }).join('') +
                        '<path d="' + escapeHtml(mainPath.join(' ')) + '" fill="none" stroke="#ffffff" stroke-width="' + (lineWidth + 4) + '" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.72" />' +
                        '<path d="' + escapeHtml(mainPath.join(' ')) + '" fill="none" stroke="' + escapeHtml(lineColor) + '" stroke-width="' + lineWidth + '" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.96" />' +
                        markerMarkup +
                    '</svg>' +
                    badgeMarkup +
                    panelMarkup +
                    attributionMarkup +
                '</div>';
        }

        function getMapPreviewLatLngs(segment) {
            var latLngs = [];

            if (!segment || !Array.isArray(segment.points)) {
                return latLngs;
            }

            segment.points.forEach(function (point) {
                var normalizedPoint = normalizeMapPreviewPoint(point);

                if (normalizedPoint) {
                    latLngs.push(normalizedPoint);
                }
            });

            return latLngs;
        }

        function getMapPreviewMiddleLatLng(segment) {
            var latLngs = getMapPreviewLatLngs(segment);

            if (!latLngs.length) {
                return null;
            }

            return latLngs[Math.floor(latLngs.length / 2)];
        }

        function clearMapPreviewLeafletLayers() {
            if (!mapPreviewLeaflet.map) {
                mapPreviewLeaflet.layers = [];
                return;
            }

            mapPreviewLeaflet.layers.forEach(function (layer) {
                if (layer && mapPreviewLeaflet.map.hasLayer(layer)) {
                    mapPreviewLeaflet.map.removeLayer(layer);
                }
            });

            mapPreviewLeaflet.layers = [];
        }

        function addMapPreviewLeafletLayer(layer) {
            if (!mapPreviewLeaflet.map || !layer) {
                return;
            }

            layer.addTo(mapPreviewLeaflet.map);
            mapPreviewLeaflet.layers.push(layer);
        }

        function refreshMapPreviewLeafletSize(centerLat, centerLng, zoom) {
            if (!mapPreviewLeaflet.map) {
                return;
            }

            centerLat = Number.isFinite(centerLat) ? centerLat : mapPreviewLeaflet.centerLat;
            centerLng = Number.isFinite(centerLng) ? centerLng : mapPreviewLeaflet.centerLng;
            zoom = Number.isFinite(zoom) ? zoom : mapPreviewLeaflet.zoom;
            zoom = Number.isFinite(zoom) ? zoom : mapPreviewLeaflet.map.getZoom();

            mapPreviewLeaflet.map.invalidateSize(false);

            if (Number.isFinite(centerLat) && Number.isFinite(centerLng)) {
                mapPreviewLeaflet.map.setView([centerLat, centerLng], zoom);
            }
        }

        function ensureMapPreviewLeaflet($root, $mapCanvas, centerLat, centerLng, zoom) {
            if (!window.L || !$mapCanvas.length) {
                return false;
            }

            mapPreviewLeaflet.centerLat = centerLat;
            mapPreviewLeaflet.centerLng = centerLng;
            mapPreviewLeaflet.zoom = zoom;

            if (!mapPreviewLeaflet.map) {
                mapPreviewLeaflet.map = window.L.map($mapCanvas[0], {
                    attributionControl: false,
                    zoomControl: true,
                    dragging: true,
                    scrollWheelZoom: true,
                    doubleClickZoom: true,
                    boxZoom: true,
                    keyboard: true,
                    tap: true
                });

                mapPreviewLeaflet.tileLayer = window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxNativeZoom: 19,
                    maxZoom: 20,
                    attribution: '&copy; OpenStreetMap contributors'
                });

                mapPreviewLeaflet.tileLayer.on('tileload load', function () {
                    mapPreviewLeaflet.tileReady = true;
                    $root.addClass('is-leaflet-ready');
                    window.setTimeout(function () {
                        if (mapPreviewLeaflet.map) {
                            mapPreviewLeaflet.map.invalidateSize(false);
                        }
                    }, 0);
                });

                mapPreviewLeaflet.tileLayer.on('tileerror', function () {
                    if (!mapPreviewLeaflet.tileReady) {
                        $root.removeClass('is-leaflet-ready');
                    }
                });

                mapPreviewLeaflet.tileLayer.addTo(mapPreviewLeaflet.map);

                if (window.ResizeObserver) {
                    mapPreviewLeaflet.resizeObserver = new ResizeObserver(function () {
                        refreshMapPreviewLeafletSize();
                    });
                    mapPreviewLeaflet.resizeObserver.observe($mapCanvas[0]);
                } else {
                    $(window).on('resize.feuMapPreview', function () {
                        refreshMapPreviewLeafletSize();
                    });
                }
            }

            mapPreviewLeaflet.map.setView([centerLat, centerLng], zoom);

            [0, 80, 240, 600].forEach(function (delay) {
                window.setTimeout(function () {
                    refreshMapPreviewLeafletSize(centerLat, centerLng, zoom);
                }, delay);
            });

            return true;
        }

        function renderMapPreviewLeafletOverlay($overlay, options) {
            if (!$overlay.length) {
                return;
            }

            var panelMarkup = '';
            var attributionMarkup = '';
            var streetLabelMarkup = '';

            if (options.showLabel && options.labelText && options.streetLabelPosition && options.streetLabelPosition !== 'auto') {
                streetLabelMarkup =
                    '<div class="feu-einsatz-map-live-preview-overlay-label is-position-' + escapeHtml(options.streetLabelPosition) + '">' +
                        escapeHtml(options.labelText) +
                    '</div>';
            }

            if (options.showPanel && (options.showPanelHeading || options.showPanelAddress)) {
                panelMarkup =
                    '<div class="feu-einsatz-map-live-preview-overlay-panel is-position-' + escapeHtml(options.panelPosition || 'bottom-left') + '">' +
                        (options.showPanelHeading ? '<strong>' + escapeHtml(options.heading || '') + '</strong>' : '') +
                        (options.showPanelAddress ? '<span>' + escapeHtml(options.address || '') + '</span>' : '') +
                    '</div>';
            }

            if (options.showAttribution) {
                attributionMarkup =
                    '<div class="feu-einsatz-map-live-preview-overlay-attribution is-position-' + escapeHtml(options.attributionPosition || 'bottom-right') + '">' +
                        escapeHtml(options.attributionText || 'Leaflet | \u00a9 OpenStreetMap contributors') +
                    '</div>';
            }

            $overlay.html(streetLabelMarkup + panelMarkup + attributionMarkup);
        }

        function renderMapPreviewLeaflet($root, options) {
            var $mapCanvas = $root.find('[data-feu-map-preview-map]').first();
            var $overlay = $root.find('[data-feu-map-preview-overlay]').first();
            var geometry = Array.isArray(options.geometry) ? options.geometry : [];
            var mainSegment = geometry.length ? geometry[0] : null;
            var zoom = clampNumber(parseInt(options.zoom || 16, 10) || 16, 11, 20);
            var station = options.station && typeof options.station === 'object' ? options.station : {};
            var labelLatLng;

            if (!ensureMapPreviewLeaflet($root, $mapCanvas, options.centerLat, options.centerLng, zoom)) {
                return;
            }

            clearMapPreviewLeafletLayers();
            renderMapPreviewLeafletOverlay($overlay, options);

            geometry.slice(1).forEach(function (segment) {
                var latLngs = getMapPreviewLatLngs(segment);
                var isPedestrian = String(segment && segment.kind || '') === 'pedestrian';

                if (latLngs.length < 2) {
                    return;
                }

                addMapPreviewLeafletLayer(window.L.polyline(latLngs, {
                    color: '#ffffff',
                    weight: isPedestrian ? 7 : 9,
                    opacity: 0.86,
                    interactive: false,
                    dashArray: isPedestrian ? '18 12' : null
                }));

                addMapPreviewLeafletLayer(window.L.polyline(latLngs, {
                    color: isPedestrian ? '#d97706' : '#d5e0ec',
                    weight: isPedestrian ? 3 : 4,
                    opacity: 0.96,
                    interactive: false,
                    dashArray: isPedestrian ? '18 12' : null
                }));
            });

            if (mainSegment) {
                var mainLatLngs = getMapPreviewLatLngs(mainSegment);

                if (mainLatLngs.length >= 2) {
                    addMapPreviewLeafletLayer(window.L.polyline(mainLatLngs, {
                        color: '#ffffff',
                        weight: options.strokeWidth + 4,
                        opacity: 0.72,
                        interactive: false
                    }));

                    addMapPreviewLeafletLayer(window.L.polyline(mainLatLngs, {
                        color: options.color,
                        weight: options.strokeWidth,
                        opacity: 0.96,
                        interactive: false
                    }));
                }
            }

            if (Array.isArray(options.marker) && options.marker.length >= 2) {
                var stationLogo = String(station.logoUrl || '').trim();
                var stationLabel = String(station.label || '').trim() || 'Feuerwehrhaus';
                var stationLogoSize = clampNumber(parseInt(station.logoSize || 40, 10) || 40, 20, 96);
                var stationHtml = stationLogo
                    ? '<span class="feu-einsatz-map-live-preview-station-logo" style="width:' + stationLogoSize + 'px;height:' + stationLogoSize + 'px;"><img src="' + escapeHtml(stationLogo) + '" alt="" /></span>'
                    : '<span class="feu-einsatz-map-live-preview-station-fallback">FW</span>';

                stationHtml += '<span class="feu-einsatz-map-live-preview-station-label">' + escapeHtml(stationLabel) + '</span>';

                addMapPreviewLeafletLayer(window.L.marker(options.marker, {
                    interactive: false,
                    icon: window.L.divIcon({
                        className: 'feu-einsatz-map-live-preview-station-icon',
                        html: '<span class="feu-einsatz-map-live-preview-station">' + stationHtml + '</span>',
                        iconSize: null,
                        iconAnchor: [Math.round(stationLogoSize / 2), Math.round(stationLogoSize / 2)]
                    })
                }));
            }

            labelLatLng = getMapPreviewMiddleLatLng(mainSegment);

            if (options.showLabel && options.labelText && labelLatLng && (!options.streetLabelPosition || options.streetLabelPosition === 'auto')) {
                addMapPreviewLeafletLayer(window.L.marker(labelLatLng, {
                    interactive: false,
                    icon: window.L.divIcon({
                        className: 'feu-einsatz-map-live-preview-leaflet-label',
                        html: '<span>' + escapeHtml(options.labelText) + '</span>',
                        iconSize: null,
                        iconAnchor: [0, 18]
                    })
                }));
            }

            window.setTimeout(function () {
                refreshMapPreviewLeafletSize(options.centerLat, options.centerLng, zoom);
            }, 0);
        }

        function updateMapPreviewLivePreview() {
            var $root = $('[data-feu-map-preview-root]');
            var $stage = $root.find('[data-feu-map-preview-stage]').first();
            var $fallback = $root.find('[data-feu-map-preview-fallback]').first();
            var centerLat;
            var centerLng;
            var geometry;
            var previewOptions;

            if (!$root.length || !$stage.length) {
                return;
            }

            var heading = $.trim($('#feu_einsatz_map_preview_heading_text').val() || '') || 'Einsatzort';
            var prefix = $.trim($('#feu_einsatz_map_preview_street_label_prefix').val() || '') || 'Einsatz Strasse';
            var showPanel = $('#feu_einsatz_map_preview_show_panel').is(':checked');
            var showPanelHeading = $('#feu_einsatz_map_preview_show_panel_heading').is(':checked');
            var showPanelAddress = $('#feu_einsatz_map_preview_show_panel_address').is(':checked');
            var panelPosition = $('#feu_einsatz_map_preview_panel_position').val() || 'bottom-left';
            var streetLabelPosition = $('#feu_einsatz_map_preview_street_label_position').val() || 'auto';
            var showAttribution = $('#feu_einsatz_map_preview_show_attribution').is(':checked');
            var attributionText = $.trim($('#feu_einsatz_map_preview_attribution_text').val() || '') || 'Leaflet | \u00a9 OpenStreetMap contributors';
            var attributionPosition = $('#feu_einsatz_map_preview_attribution_position').val() || 'bottom-right';
            var color = $('#feu_einsatz_map_preview_highlight_color').val() || '#d92d20';
            var strokeWidth = parseInt($('#feu_einsatz_map_preview_stroke_width').val() || '8', 10);
            var showLabel = $('#feu_einsatz_map_preview_show_street_label').is(':checked');
            var stationStreet = $.trim($('#feu_einsatz_area_station_street').val() || '');
            var stationPostcode = $.trim($('#feu_einsatz_area_station_postcode').val() || '');
            var stationCity = $.trim($('#feu_einsatz_area_station_city').val() || '') || 'Hamburg';
            var zoom = parseInt($('#feu_einsatz_map_zoom').val() || '16', 10);
            var mapHeight = parseInt($('#feu_einsatz_map_height').val() || '400', 10);
            var previewStreet = stationStreet ? $.trim(String(stationStreet).split(',')[0]) : 'Beispielstrasse';
            previewStreet = $.trim(String(previewStreet).replace(/\s+\d+[a-zA-Z\-\/]*\s*$/, ''));
            if (!previewStreet) {
                previewStreet = 'Beispielstrasse';
            }
            var previewAddress = [stationStreet || previewStreet, $.trim([stationPostcode, stationCity].join(' '))].filter(function (part) {
                return !!$.trim(part);
            }).join(', ');
            var $fontOption = $('#feu_einsatz_map_preview_font_family option:selected');
            var fontCss = $fontOption.attr('data-feu-map-font-css') || '"Segoe UI", Arial, sans-serif';
            var $strokeValue = $('[data-feu-map-preview-stroke-value]');
            var $zoomValue = $root.find('[data-feu-map-preview-zoom]');
            var $heightValue = $root.find('[data-feu-map-preview-height-value]');
            var $stationLogoSizeValue = $('[data-feu-station-logo-size-value]');
            var stationLogoSize = parseInt($('#feu_einsatz_area_station_logo_size').val() || '40', 10);
            var stationLogoUrl = $('#feu-einsatz-area-station-logo-preview img').first().attr('src')
                || $stage.attr('data-feu-map-preview-station-logo')
                || '';
            var stationLabel = $.trim($('#feu_einsatz_photo_watermark_text').val() || '')
                || $.trim($stage.attr('data-feu-map-preview-station-label') || '')
                || 'Feuerwehrhaus';

            if (!strokeWidth || strokeWidth < 3) {
                strokeWidth = 8;
            }

            if (!mapHeight || mapHeight < 320) {
                mapHeight = 400;
            }

            mapHeight = Math.max(320, Math.min(680, mapHeight));

            if (!previewAddress) {
                previewAddress = 'Beispielstrasse, 22547 Hamburg';
            }

            $root.css('--feu-map-preview-line-color', color);
            $root.css('--feu-map-preview-line-width', strokeWidth + 'px');
            $root.css('--feu-map-preview-font-stack', fontCss);
            $root.css('--feu-map-preview-height', mapHeight + 'px');

            if ($strokeValue.length) {
                $strokeValue.text(strokeWidth);
            }

            if ($zoomValue.length) {
                $zoomValue.text(Number.isFinite(zoom) ? zoom : 16);
            }

            if ($heightValue.length) {
                $heightValue.text(mapHeight);
            }

            if ($stationLogoSizeValue.length) {
                $stationLogoSizeValue.text(Math.max(20, Math.min(96, isNaN(stationLogoSize) ? 40 : stationLogoSize)));
            }

            centerLat = parseFloat($stage.attr('data-feu-map-preview-center-lat') || '53.5853');
            centerLng = parseFloat($stage.attr('data-feu-map-preview-center-lng') || '9.8827');

            if (!Number.isFinite(centerLat) || !Number.isFinite(centerLng)) {
                centerLat = 53.5853;
                centerLng = 9.8827;
            }

            geometry = readMapPreviewGeometry($stage, stationStreet, stationPostcode, stationCity);

            if (!geometry.length) {
                geometry = buildMapPreviewFallbackGeometry(centerLat, centerLng);
            }

            previewOptions = {
                height: mapHeight,
                color: color,
                strokeWidth: strokeWidth,
                zoom: zoom,
                heading: heading,
                address: previewAddress,
                showPanel: showPanel,
                showPanelHeading: showPanelHeading,
                showPanelAddress: showPanelAddress,
                panelPosition: panelPosition,
                labelText: prefix + ': ' + previewStreet,
                showLabel: showLabel,
                streetLabelPosition: streetLabelPosition,
                showAttribution: showAttribution,
                attributionText: attributionText,
                attributionPosition: attributionPosition,
                geometry: geometry,
                marker: [centerLat, centerLng],
                station: {
                    label: stationLabel,
                    logoUrl: stationLogoUrl,
                    logoSize: stationLogoSize
                },
                centerLat: centerLat,
                centerLng: centerLng
            };

            if ($fallback.length) {
                $fallback.html(buildMapPreviewFallbackMarkup(previewOptions));
            } else {
                $stage.html(buildMapPreviewFallbackMarkup(previewOptions));
            }

            renderMapPreviewLeaflet($root, previewOptions);
        }

        updateMapPreviewLivePreview();

        document.addEventListener('feu:einsatz-settings-tab-activated', function (event) {
            var detail = event && event.detail ? event.detail : {};
            var tab = String(detail.tab || '');

            [80, 220].forEach(function (delay) {
                window.setTimeout(function () {
                    if (tab === 'karten') {
                        updateMapPreviewLivePreview();
                    }

                    if (tab === 'sozial') {
                        updateSocialShareLivePreview();
                    }
                }, delay);
            });
        });

        window.setTimeout(updateMapPreviewLivePreview, 80);
        window.setTimeout(updateMapPreviewLivePreview, 240);

        function generateMapImage($button) {
            var postId = $('#post_ID').val() || $('input[name="post_id"]').val();
            var strasse = $('#feu_einsatz_strasse').val();
            var hausnummer = $('#feu_einsatz_hausnummer').val();
            var plz = $('#feu_einsatz_plz').val();
            var stadt = $('#feu_einsatz_stadt').val() || 'Hamburg';
            var latitude = $('#feu_einsatz_latitude').val();
            var longitude = $('#feu_einsatz_longitude').val();
            var originalText = $button.text();
            var address = buildAddress(strasse, hausnummer, plz, stadt);
            var hasCoordinates = !!normalizeCoordinate(latitude) && !!normalizeCoordinate(longitude);

            if (!postId) {
                alert('Bitte speichern Sie zuerst den Beitrag.');
                return;
            }

            if (!strasse && !hasCoordinates) {
                alert('Bitte hinterlegen Sie mindestens eine Strasse oder gueltige Koordinaten.');
                return;
            }

            $button.prop('disabled', true).text('Karten-Vorschau wird generiert...');

            $.ajax({
                url: feu_einsatz_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'feu_einsatz_generate_map_image',
                    nonce: feu_einsatz_ajax.nonce,
                    post_id: postId,
                    address: address,
                    strasse: strasse,
                    hausnummer: hausnummer,
                    plz: plz,
                    stadt: stadt,
                    latitude: latitude,
                    longitude: longitude
                },
                timeout: 30000,
                success: function (response) {
                    if (response.success) {
                        showNotice('success', response.data.message);

                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotice('error', 'Fehler: ' + (response.data.message || 'Unbekannter Fehler'));
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function (xhr, status, error) {
                    var errorMessage = 'Verbindungsfehler';
                    var responseJson = xhr && xhr.responseJSON ? xhr.responseJSON : null;

                    if (responseJson && responseJson.data && responseJson.data.message) {
                        errorMessage = responseJson.data.message;
                    }

                    if (!responseJson && status === 'timeout') {
                        errorMessage = 'Zeitueberschreitung - Der Server antwortet nicht';
                    } else if (!responseJson && status === 'error' && error) {
                        errorMessage = 'Serverfehler: ' + error;
                    }

                    console.error('AJAX error', status, error, xhr.responseText);
                    showNotice('error', errorMessage);
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }

        function buildAddress(strasse, hausnummer, plz, stadt) {
            var parts = [];
            var streetAddress = [strasse, hausnummer].filter(function (value) {
                return !!$.trim(value || '');
            }).join(' ');

            if (streetAddress) {
                parts.push(streetAddress);
            }

            if (plz) {
                parts.push(plz);
            }

            if (stadt) {
                parts.push(stadt);
            }

            parts.push('Deutschland');
            return parts.join(', ');
        }

        function normalizeCoordinate(value) {
            if (!value) {
                return '';
            }

            value = String(value).replace(',', '.').trim();

            return /^-?\d+(?:\.\d+)?$/.test(value) ? value : '';
        }

        function getMediaApi() {
            if (window.wp && window.wp.media) {
                return window.wp.media;
            }

            if (window.parent && window.parent.wp && window.parent.wp.media) {
                return window.parent.wp.media;
            }

            if (window.top && window.top.wp && window.top.wp.media) {
                return window.top.wp.media;
            }

            return null;
        }

        function getGalleryIds() {
            var value = $('#feu_einsatz_gallery_ids').val();

            if (!value) {
                return [];
            }

            return value.split(',').map(function (id) {
                return id.trim();
            }).filter(function (id) {
                return id !== '';
            });
        }

        function setGalleryIds(ids) {
            $('#feu_einsatz_gallery_ids').val(ids.join(','));
        }

        function appendGalleryItem(id, imageUrl) {
            var html = '' +
                '<div class="feu-einsatz-gallery-item" data-id="' + id + '">' +
                '<img src="' + imageUrl + '" alt="" class="feu-einsatz-gallery-thumb" />' +
                '<button type="button" class="button-link-delete feu-einsatz-remove-gallery-image feu-einsatz-gallery-remove-button">x</button>' +
                '</div>';

            $('#feu-einsatz-gallery-preview').append(html);
        }

        function showNotice(type, message) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p></p></div>');

            notice.find('p').text(message || '');

            $('.feu-einsatz-meta-box').first().before(notice);

            setTimeout(function () {
                notice.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 5000);
        }

    });
})(jQuery);
