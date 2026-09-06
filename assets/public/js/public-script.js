(function () {
    'use strict';

    function getStrings() {
        if (window.feu_einsatz_public && window.feu_einsatz_public.strings) {
            return window.feu_einsatz_public.strings;
        }

        return {
            mapUnavailable: 'Kartenansicht nicht verfügbar.',
            mapUnavailableHint: 'Für diesen Einsatz sind noch keine ausreichenden Kartendaten gespeichert.',
            pedestrianPartLabel: 'Fußgängerbereich',
            streetFallback: 'Straße',
            mapConsentTitle: 'Datenschutz-Hinweis',
            mapConsentText: 'Beim Laden der Live-Karte werden externe Kartendaten von OpenStreetMap nachgeladen.',
            mapConsentAllow: 'Live-Karte laden',
            mapConsentDecline: 'Nicht laden',
            mapPrivacyRejected: 'Karte nicht geladen',
            mapPrivacyRejectedHint: 'Sie haben das Nachladen externer Kartendaten abgelehnt.'
        };
    }

    function normalizeNumber(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        var normalized = String(value).replace(',', '.');
        var parsed = parseFloat(normalized);

        return Number.isFinite(parsed) ? parsed : null;
    }

    function normalizePoint(point) {
        if (!Array.isArray(point) || point.length < 2) {
            return null;
        }

        var lat = normalizeNumber(point[0]);
        var lng = normalizeNumber(point[1]);

        if (lat === null || lng === null) {
            return null;
        }

        return [lat, lng];
    }

    function isPedestrianHighway(highway) {
        return [
            'pedestrian',
            'footway',
            'path',
            'steps',
            'corridor',
            'cycleway',
            'track'
        ].indexOf(String(highway || '').toLowerCase()) !== -1;
    }

    function isCoordinatePair(value) {
        return Array.isArray(value)
            && value.length >= 2
            && normalizeNumber(value[0]) !== null
            && normalizeNumber(value[1]) !== null;
    }

    function normalizeGeoJsonPoints(coordinates) {
        return (Array.isArray(coordinates) ? coordinates : []).map(function (coordinate) {
            if (!isCoordinatePair(coordinate)) {
                return null;
            }

            // GeoJSON uses longitude, latitude while Leaflet expects latitude, longitude.
            return normalizePoint([coordinate[1], coordinate[0]]);
        }).filter(Boolean);
    }

    function getSegmentKind(segment, fallbackKind) {
        var kind = fallbackKind || 'road';

        if (segment && typeof segment === 'object'
            && (String(segment.kind || '').toLowerCase() === 'pedestrian' || isPedestrianHighway(segment.highway))) {
            kind = 'pedestrian';
        }

        return kind;
    }

    function normalizeGeoJsonGeometry(geometry, fallbackKind) {
        if (!geometry || typeof geometry !== 'object') {
            return [];
        }

        if (geometry.type === 'Feature') {
            return normalizeGeoJsonGeometry(geometry.geometry, getSegmentKind(geometry.properties, fallbackKind));
        }

        if (geometry.type === 'FeatureCollection') {
            return (Array.isArray(geometry.features) ? geometry.features : []).reduce(function (segments, feature) {
                return segments.concat(normalizeGeoJsonGeometry(feature, fallbackKind));
            }, []);
        }

        if (geometry.type === 'GeometryCollection') {
            return (Array.isArray(geometry.geometries) ? geometry.geometries : []).reduce(function (segments, item) {
                return segments.concat(normalizeGeoJsonGeometry(item, fallbackKind));
            }, []);
        }

        var kind = getSegmentKind(geometry, fallbackKind);
        var coordinates = geometry.coordinates;
        var lines = [];

        if (geometry.type === 'LineString') {
            lines = [coordinates];
        } else if (geometry.type === 'MultiLineString') {
            lines = coordinates;
        } else if (geometry.type === 'Polygon') {
            // This keeps route data visible if an older cache contains an outer polygon ring.
            lines = Array.isArray(coordinates) && coordinates.length ? [coordinates[0]] : [];
        } else if (geometry.type === 'MultiPolygon') {
            lines = (Array.isArray(coordinates) ? coordinates : []).map(function (polygon) {
                return Array.isArray(polygon) && polygon.length ? polygon[0] : [];
            });
        } else {
            return [];
        }

        return (Array.isArray(lines) ? lines : []).map(function (line) {
            var points = normalizeGeoJsonPoints(line);

            return points.length >= 2 ? { points: points, kind: kind } : null;
        }).filter(Boolean);
    }

    function normalizeGeometry(geometry) {
        // Saved reports can contain legacy segment arrays or cached GeoJSON payloads.
        if (geometry && typeof geometry === 'object' && !Array.isArray(geometry)) {
            if (geometry.type) {
                return normalizeGeoJsonGeometry(geometry);
            }

            if (geometry.geometry) {
                return normalizeGeometry(geometry.geometry);
            }
        }

        var source = Array.isArray(geometry) ? geometry : [];

        // A raw coordinate line is a compact representation of a single route segment.
        if (source.length && isCoordinatePair(source[0])) {
            source = [source];
        }

        return source.reduce(function (segments, segment) {
            var pointsSource = segment;
            var kind = 'road';

            if (segment && typeof segment === 'object' && !Array.isArray(segment)) {
                if (segment.type) {
                    return segments.concat(normalizeGeoJsonGeometry(segment));
                }

                pointsSource = Array.isArray(segment.points) ? segment.points : [];
                kind = getSegmentKind(segment, kind);
            }

            var points = (Array.isArray(pointsSource) ? pointsSource : []).map(normalizePoint).filter(Boolean);

            if (points.length < 2) {
                return segments;
            }

            segments.push({
                points: points,
                kind: kind
            });

            return segments;
        }, []);
    }

    function normalizeCenter(center) {
        if (!Array.isArray(center) || center.length < 2) {
            return null;
        }

        var lat = normalizeNumber(center[0]);
        var lng = normalizeNumber(center[1]);

        if (lat === null || lng === null) {
            return null;
        }

        return [lat, lng];
    }

    function normalizeStation(station) {
        if (!station || typeof station !== 'object') {
            return null;
        }

        var lat = normalizeNumber(station.latitude);
        var lng = normalizeNumber(station.longitude);

        if (lat === null || lng === null) {
            return null;
        }

        return {
            label: String(station.label || 'Feuerwehrhaus').trim() || 'Feuerwehrhaus',
            address: String(station.address || '').trim(),
            logoUrl: String(station.logo_url || '').trim(),
            logoSize: Math.max(20, Math.min(96, parseInt(station.logo_size || '40', 10) || 40)),
            point: [lat, lng]
        };
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getStreetLabel(config, strings) {
        var street = config && typeof config.street === 'string' ? config.street.trim() : '';

        return street || strings.streetFallback;
    }

    function createStreetLayers(map, geometry, config) {
        var strings = getStrings();
        var streetLabel = getStreetLabel(config, strings);
        var bounds = window.L.latLngBounds([]);
        var orderedGeometry = geometry.slice().sort(function (left, right) {
            if (left.kind === right.kind) {
                return 0;
            }

            return left.kind === 'road' ? -1 : 1;
        });

        orderedGeometry.forEach(function (segment) {
            bounds.extend(window.L.latLngBounds(segment.points));
        });

        var strokeWidth = Math.max(3, Math.min(18, parseInt(config && config.stroke_width, 10) || 8));
        var pedestrianStrokeWidth = Math.max(2, strokeWidth - 3);
        var roadGlowWidth = strokeWidth + 6;
        var pedestrianGlowWidth = pedestrianStrokeWidth + 3;

        var roadSegments = orderedGeometry.filter(function (s) { return s.kind === 'road'; });
        var pedestrianSegments = orderedGeometry.filter(function (s) { return s.kind === 'pedestrian'; });
        var roadPoints = roadSegments.map(function (s) { return s.points; });
        var pedestrianPoints = pedestrianSegments.map(function (s) { return s.points; });

        // Glow — один мультиполилайн на каждый тип, без стыков
        if (roadPoints.length) {
            window.L.polyline(roadPoints, {
                color: '#ffffff',
                weight: roadGlowWidth,
                opacity: 0.72,
                lineCap: 'round',
                lineJoin: 'round',
                smoothFactor: 0,
                interactive: false
            }).addTo(map);
        }
        if (pedestrianPoints.length) {
            window.L.polyline(pedestrianPoints, {
                color: '#ffffff',
                weight: pedestrianGlowWidth,
                opacity: 0.72,
                lineCap: 'round',
                lineJoin: 'round',
                smoothFactor: 0,
                interactive: false,
                dashArray: '18 12'
            }).addTo(map);
        }

        // Highlight — посегментно для тултипов
        var labelStyle = (config && config.label_style) || 'bubble';
        var labelTextColor = (config && config.label_text_color) || '#ffffff';
        var highlightColor = (config && config.highlight_color) || '#d92d20';

        orderedGeometry.forEach(function (segment) {
            var labelText = segment.kind === 'pedestrian'
                ? strings.pedestrianPartLabel + ': ' + streetLabel
                : streetLabel;
            var tooltipContent;
            if (labelStyle === 'plain') {
                tooltipContent = '<span class="feu-einsatz-map-label feu-einsatz-map-label--plain">' + escapeHtml(labelText) + '</span>';
            } else if (labelStyle === 'badge') {
                tooltipContent = '<span class="feu-einsatz-map-label feu-einsatz-map-label--badge" style="background:' + escapeHtml(highlightColor) + ';color:' + escapeHtml(labelTextColor) + '">' + escapeHtml(labelText) + '</span>';
            } else {
                tooltipContent = '<span class="feu-einsatz-map-label feu-einsatz-map-label--bubble" style="background:' + escapeHtml(highlightColor) + ';color:' + escapeHtml(labelTextColor) + '">' + escapeHtml(labelText) + '</span>';
            }
            var line = window.L.polyline(segment.points, {
                color: segment.kind === 'pedestrian' ? '#d97706' : highlightColor,
                weight: segment.kind === 'pedestrian' ? pedestrianStrokeWidth : strokeWidth,
                opacity: 0.95,
                lineCap: 'round',
                lineJoin: 'round',
                smoothFactor: 0,
                interactive: true,
                dashArray: segment.kind === 'pedestrian' ? '18 12' : null
            });

            line.bindTooltip(tooltipContent, {
                sticky: true,
                direction: 'top',
                opacity: 1,
                className: 'feu-einsatz-map-label-tooltip'
            });
            line.addTo(map);
        });

        // Permanent label at route midpoint
        var allRoutePoints = [];
        orderedGeometry.forEach(function (segment) {
            segment.points.forEach(function (pt) { allRoutePoints.push(pt); });
        });
        if (allRoutePoints.length > 0) {
            var midPoint = allRoutePoints[Math.floor(allRoutePoints.length / 2)];
            var permanentLabelText = streetLabel;
            var permanentLabelContent;
            if (labelStyle === 'plain') {
                permanentLabelContent = '<span class="feu-einsatz-map-label feu-einsatz-map-label--plain">' + escapeHtml(permanentLabelText) + '</span>';
            } else if (labelStyle === 'badge') {
                permanentLabelContent = '<span class="feu-einsatz-map-label feu-einsatz-map-label--badge" style="background:' + escapeHtml(highlightColor) + ';color:' + escapeHtml(labelTextColor) + '">' + escapeHtml(permanentLabelText) + '</span>';
            } else {
                permanentLabelContent = '<span class="feu-einsatz-map-label feu-einsatz-map-label--bubble" style="background:' + escapeHtml(highlightColor) + ';color:' + escapeHtml(labelTextColor) + '">' + escapeHtml(permanentLabelText) + '</span>';
            }
            window.L.marker(midPoint, {
                icon: window.L.divIcon({ className: '', html: '', iconSize: [0, 0], iconAnchor: [0, 0] }),
                interactive: false,
                keyboard: false
            }).bindTooltip(permanentLabelContent, {
                permanent: true,
                direction: 'top',
                offset: [0, -4],
                opacity: 1,
                className: 'feu-einsatz-map-label-tooltip'
            }).addTo(map);
        }

        return bounds;
    }

    function addStationLayer(map, config, bounds) {
        var station = normalizeStation(config && config.station);
        var marker;
        var baseIconSize;

        if (!station) {
            return;
        }

        baseIconSize = Math.max(34, Math.min(82, station.logoSize + 18));

        function buildIconHtml(size) {
            return station.logoUrl
                ? '<div class="feu-einsatz-area-station-icon-shell"><div class="feu-einsatz-area-station-icon" style="width:' + size + 'px;height:' + size + 'px;"><img src="' + escapeHtml(station.logoUrl) + '" alt="" /></div></div>'
                : '<div class="feu-einsatz-area-station-icon-shell"><div class="feu-einsatz-area-station-icon" style="width:' + size + 'px;height:' + size + 'px;"><span>FF</span></div></div>';
        }

        function getScaledSize(zoom) {
            if (zoom >= 15) { return baseIconSize; }
            if (zoom >= 13) { return Math.round(baseIconSize * 0.7); }
            if (zoom >= 11) { return Math.round(baseIconSize * 0.5); }
            return Math.round(baseIconSize * 0.35);
        }

        function updateMarkerIcon(zoom) {
            var size = getScaledSize(zoom);
            marker.setIcon(window.L.divIcon({
                className: 'feu-einsatz-area-station-icon-shell',
                html: buildIconHtml(size),
                iconSize: null,
                iconAnchor: [Math.round(size / 2), Math.round(size / 2)]
            }));
        }

        var initialSize = getScaledSize(map.getZoom());
        marker = window.L.marker(station.point, {
            icon: window.L.divIcon({
                className: 'feu-einsatz-area-station-icon-shell',
                html: buildIconHtml(initialSize),
                iconSize: null,
                iconAnchor: [Math.round(initialSize / 2), Math.round(initialSize / 2)]
            }),
            interactive: false
        }).addTo(map);

        marker.bindTooltip(station.label, {
            permanent: true,
            direction: 'right',
            offset: [16, 0],
            className: 'feu-einsatz-area-station-tooltip',
            opacity: 0.96
        });

        map.on('zoomend', function () {
            var zoom = map.getZoom();
            updateMarkerIcon(zoom);
            var tooltip = marker.getTooltip();
            if (tooltip) {
                if (zoom < 11) {
                    marker.closeTooltip();
                } else {
                    marker.openTooltip();
                }
            }
        });
    }

    function showReady(runtime) {
        runtime.classList.add('is-ready');
        runtime.classList.remove('has-error');
    }

    function showFallback(runtime, coordinates) {
        var strings = getStrings();
        runtime.classList.add('has-error');

        var fallback = runtime.querySelector('.feu-einsatz-map-runtime-fallback');
        var placeholderCoordinates = fallback ? fallback.querySelector('.feu-einsatz-map-placeholder-coordinates') : null;

        if (!fallback) {
            return;
        }

        fallback.hidden = false;

        if (placeholderCoordinates && Array.isArray(coordinates) && coordinates.length >= 2) {
            placeholderCoordinates.textContent = coordinates[0].toFixed(6) + ', ' + coordinates[1].toFixed(6);
        }

        var placeholder = fallback.querySelector('.feu-einsatz-map-placeholder');

        if (!placeholder) {
            fallback.innerHTML = '<div class="feu-einsatz-map-placeholder"><strong>' + escapeHtml(strings.mapUnavailable) + '</strong><p>' + escapeHtml(strings.mapUnavailableHint) + '</p></div>';
        }
    }

    function showDeclinedState(runtime) {
        var strings = getStrings();
        var privacyMode = String(runtime.getAttribute('data-map-privacy-mode') || 'always');
        var fallback = runtime.querySelector('.feu-einsatz-map-runtime-fallback');
        var consentBox = runtime.querySelector('[data-feu-map-consent]');
        var previewBox = runtime.querySelector('[data-feu-map-preview]');
        var rejectedBox = runtime.querySelector('[data-feu-map-rejected]');

        runtime.classList.add('has-error');
        runtime.classList.remove('is-ready');

        if (!fallback) {
            return;
        }

        fallback.hidden = false;

        if (consentBox) {
            consentBox.hidden = true;
        }

        if (privacyMode === 'consent_image' && previewBox) {
            previewBox.hidden = false;

            if (rejectedBox) {
                rejectedBox.hidden = true;
            }

            return;
        }

        if (previewBox) {
            previewBox.hidden = true;
        }

        if (rejectedBox) {
            rejectedBox.hidden = false;
            return;
        }

        fallback.innerHTML = '<div class="feu-einsatz-map-placeholder"><strong>' + escapeHtml(strings.mapPrivacyRejected) + '</strong><p>' + escapeHtml(strings.mapPrivacyRejectedHint) + '</p></div>';
    }

    function renderMap(runtime) {
        if (!window.L) {
            showFallback(runtime, null);
            return;
        }

        var canvas = runtime.querySelector('.feu-einsatz-live-map');
        var configElement = runtime.querySelector('.feu-einsatz-map-config');

        if (!canvas || !configElement) {
            showFallback(runtime, null);
            return;
        }

        var config = {};

        try {
            config = JSON.parse(configElement.textContent || '{}');
        } catch (error) {
            showFallback(runtime, null);
            return;
        }

        var geometry = normalizeGeometry(config.geometry);
        var center = normalizeCenter(config.center);
        var latitude = normalizeNumber(config.latitude);
        var longitude = normalizeNumber(config.longitude);
        var zoom = Math.max(10, Math.min(18, parseInt(config.zoom, 10) || 16));

        if (latitude !== null && longitude !== null) {
            center = [latitude, longitude];
        }

        if (!geometry.length && !center) {
            showFallback(runtime, null);
            return;
        }

        var map = window.L.map(canvas, {
            zoomControl: true,
            scrollWheelZoom: false,
            preferCanvas: true
        });

        var loadBar     = runtime.querySelector('.feu-einsatz-map-load-bar');
        var loadBarFill = loadBar ? loadBar.querySelector('.feu-einsatz-map-load-bar-fill') : null;
        var tileTotal   = 0;
        var tileLoaded  = 0;

        var tileLayer = window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        if (loadBar) {
            tileLayer.on('tileloadstart', function () {
                tileTotal++;
                if (tileTotal === 1 && loadBar) {
                    if (loadBarFill) { loadBarFill.style.width = '15%'; }
                    loadBar.classList.add('is-tracking');
                }
            });
            tileLayer.on('tileload tileerror', function () {
                tileLoaded++;
                if (tileTotal > 0 && loadBarFill) {
                    var pct = Math.min(95, Math.round(tileLoaded / tileTotal * 100));
                    loadBarFill.style.width = pct + '%';
                    loadBar.setAttribute('aria-valuenow', pct);
                }
            });
            tileLayer.on('load', function () {
                if (loadBarFill) { loadBarFill.style.width = '100%'; }
                loadBar.setAttribute('aria-valuenow', 100);
                window.setTimeout(function () { loadBar.classList.add('is-done'); }, 500);
            });
        }

        var bounds = window.L.latLngBounds([]);

        if (geometry.length) {
            bounds = createStreetLayers(map, geometry, config);
        }

        addStationLayer(map, config, bounds);

        if (bounds.isValid()) {
            map.fitBounds(bounds.pad(0.16), {
                maxZoom: zoom
            });
        } else if (center) {
            map.setView(center, zoom);
        } else {
            showFallback(runtime, null);
            return;
        }

        window.setTimeout(function () {
            map.invalidateSize();
        }, 120);

        showReady(runtime);
    }

    function initMapConsent(runtime) {
        var allowButton = runtime.querySelector('[data-feu-map-action="allow"]');
        var declineButton = runtime.querySelector('[data-feu-map-action="decline"]');

        if (allowButton) {
            allowButton.addEventListener('click', function () {
                runtime.setAttribute('data-map-enabled', '1');
                renderMap(runtime);
            });
        }

        if (declineButton) {
            declineButton.addEventListener('click', function () {
                runtime.setAttribute('data-map-enabled', '0');
                showDeclinedState(runtime);
            });
        }
    }

    function initMaps() {
        var runtimes = document.querySelectorAll('.feu-einsatz-map-runtime');

        runtimes.forEach(function (runtime) {
            var displayMode = String(runtime.getAttribute('data-map-display-mode') || 'live');
            var privacyMode = String(runtime.getAttribute('data-map-privacy-mode') || 'always');
            var liveAvailable = runtime.getAttribute('data-map-live-available') === '1';
            var enabled = runtime.getAttribute('data-map-enabled') === '1';

            if (displayMode !== 'live') {
                return;
            }

            if (!liveAvailable) {
                showFallback(runtime, null);
                return;
            }

            if (privacyMode !== 'always') {
                initMapConsent(runtime);

                if (!enabled) {
                    return;
                }
            }

            var scheduleInit = typeof window.requestIdleCallback === 'function'
                ? function (fn) { window.requestIdleCallback(fn, { timeout: 400 }); }
                : function (fn) { window.setTimeout(fn, 50); };

            scheduleInit(function () {
                try {
                    renderMap(runtime);
                } catch (error) {
                    showFallback(runtime, null);
                }
            });
        });
    }

    function flashShareButtonState(trigger, label, isError) {
        if (!trigger) {
            return;
        }

        if (trigger.getAttribute('data-feu-icon-only') === '1') {
            var defaultTitle = trigger.getAttribute('data-feu-default-title')
                || trigger.getAttribute('title')
                || trigger.getAttribute('aria-label')
                || '';

            trigger.setAttribute('data-feu-default-title', defaultTitle);
            trigger.classList.remove('is-success', 'is-error', 'is-fallback-used');
            trigger.classList.add(isError ? 'is-error' : 'is-success');

            if (label) {
                trigger.setAttribute('title', label);
                trigger.setAttribute('aria-label', label);
            }

            window.setTimeout(function () {
                trigger.classList.remove('is-success', 'is-error', 'is-fallback-used');
                trigger.setAttribute('title', defaultTitle);
                trigger.setAttribute('aria-label', defaultTitle);
            }, 1800);

            return;
        }

        var defaultLabel = trigger.getAttribute('data-feu-default-label') || trigger.textContent;
        trigger.setAttribute('data-feu-default-label', defaultLabel);
        trigger.textContent = label || defaultLabel;
        trigger.classList.remove('is-success', 'is-error');
        trigger.classList.add(isError ? 'is-error' : 'is-success');

        window.setTimeout(function () {
            trigger.textContent = defaultLabel;
            trigger.classList.remove('is-success', 'is-error');
        }, 1800);
    }

    function buildShareFileName(title, mimeType) {
        var safeTitle = String(title || 'einsatzbericht')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'einsatzbericht';
        var extension = 'png';

        if (mimeType === 'image/jpeg') {
            extension = 'jpg';
        } else if (mimeType === 'image/webp') {
            extension = 'webp';
        }

        return safeTitle + '.' + extension;
    }

    function convertBlobToJpeg(blob, title) {
        if (!blob || typeof window.File !== 'function' || typeof window.Image !== 'function') {
            return Promise.resolve(null);
        }

        return new Promise(function (resolve) {
            var objectUrl = window.URL.createObjectURL(blob);
            var image = new window.Image();

            image.onload = function () {
                var canvas = document.createElement('canvas');
                var context;

                canvas.width = image.naturalWidth || image.width;
                canvas.height = image.naturalHeight || image.height;
                context = canvas.getContext('2d');

                if (!context || !canvas.width || !canvas.height) {
                    window.URL.revokeObjectURL(objectUrl);
                    resolve(null);
                    return;
                }

                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(image, 0, 0, canvas.width, canvas.height);

                canvas.toBlob(function (jpegBlob) {
                    window.URL.revokeObjectURL(objectUrl);

                    if (!jpegBlob) {
                        resolve(null);
                        return;
                    }

                    resolve(new window.File([
                        jpegBlob
                    ], buildShareFileName(title, 'image/jpeg'), {
                        type: 'image/jpeg'
                    }));
                }, 'image/jpeg', 0.92);
            };

            image.onerror = function () {
                window.URL.revokeObjectURL(objectUrl);
                resolve(null);
            };

            image.src = objectUrl;
        });
    }

    function fetchShareFile(fileUrl, title, preferredMimeType) {
        if (!fileUrl || typeof window.File !== 'function') {
            return Promise.resolve(null);
        }

        return fetch(fileUrl, {
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('share file fetch failed');
            }

            return response.blob();
        }).then(function (blob) {
            if (!blob || !blob.size) {
                return null;
            }

            if (preferredMimeType === 'image/jpeg' && blob.type !== 'image/jpeg') {
                return convertBlobToJpeg(blob, title).then(function (jpegFile) {
                    if (jpegFile) {
                        return jpegFile;
                    }

                    return new window.File([
                        blob
                    ], buildShareFileName(title, blob.type || 'image/png'), {
                        type: blob.type || 'image/png'
                    });
                });
            }

            return new window.File([
                blob
            ], buildShareFileName(title, blob.type || 'image/png'), {
                type: blob.type || 'image/png'
            });
        }).catch(function () {
            return null;
        });
    }

    function buildNativeShareData(trigger) {
        var service = String(trigger.getAttribute('data-feu-share-service') || '').trim();
        var title = String(trigger.getAttribute('data-feu-share-title') || '').trim();
        var text = String(trigger.getAttribute('data-feu-share-text') || '').trim();
        var url = String(trigger.getAttribute('data-feu-share-url') || '').trim();
        var fileUrl = String(trigger.getAttribute('data-feu-share-file') || '').trim();
        var includeFileUrl = trigger.getAttribute('data-feu-share-use-file-url') === '1';
        var preferredMimeType = String(trigger.getAttribute('data-feu-share-prefer-mime') || '').trim();
        var shareData = {};

        if (title) {
            shareData.title = title;
        }

        if (text) {
            shareData.text = text;
        }

        if (!fileUrl) {
            if (url) {
                shareData.url = url;
            }

            return Promise.resolve(shareData);
        }

        return fetchShareFile(fileUrl, title, preferredMimeType).then(function (file) {
            if (file && typeof navigator.canShare === 'function' && navigator.canShare({ files: [file] })) {
                if (service === 'instagram') {
                    delete shareData.url;
                    shareData.files = [file];
                    return shareData;
                }

                if (url && includeFileUrl && shareData.text.indexOf(url) === -1) {
                    shareData.text = shareData.text ? shareData.text + '\n\n' + url : url;
                }

                shareData.files = [file];
                delete shareData.url;
                return shareData;
            }

            if (url) {
                shareData.url = url;
            }

            return shareData;
        });
    }

    function initNativeShareButtons() {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-feu-native-share]');

            if (!trigger) {
                return;
            }

            event.preventDefault();

            if (trigger.disabled) {
                return;
            }

            if (typeof navigator.share !== 'function') {
                flashShareButtonState(
                    trigger,
                    trigger.getAttribute('data-feu-share-error') || 'Teilen nicht verfügbar',
                    true
                );
                return;
            }

            buildNativeShareData(trigger).then(function (shareData) {
                return navigator.share(shareData);
            }).then(function () {
                flashShareButtonState(
                    trigger,
                    trigger.getAttribute('data-feu-share-success') || 'Teilen gestartet',
                    false
                );
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                flashShareButtonState(
                    trigger,
                    trigger.getAttribute('data-feu-share-error') || 'Teilen nicht verfügbar',
                    true
                );
            });
        });
    }

    function feedbackCopyButton(button, success) {
        var original = button.getAttribute('data-feu-copy-original');

        if (!original) {
            original = button.textContent || '';
            button.setAttribute('data-feu-copy-original', original);
        }

        button.textContent = success ? 'Kopiert' : 'Fehler';
        button.disabled = true;

        window.setTimeout(function () {
            button.textContent = original;
            button.disabled = false;
        }, 2000);
    }

    function fallbackCopyText(text, button) {
        var textarea = document.createElement('textarea');
        var success = false;

        textarea.value = text;
        textarea.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            success = document.execCommand('copy');
        } catch (error) {
            success = false;
        }

        document.body.removeChild(textarea);
        feedbackCopyButton(button, success);
    }

    function initShareCopyButtons() {
        document.querySelectorAll('[data-feu-copy-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.getAttribute('data-feu-copy-target');
                var target = id ? document.getElementById(id) : null;
                var text = target ? String(target.textContent || target.innerText || '').trim() : '';

                if (!text) {
                    return;
                }

                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(text)
                        .then(function () {
                            feedbackCopyButton(button, true);
                        })
                        .catch(function () {
                            fallbackCopyText(text, button);
                        });
                    return;
                }

                fallbackCopyText(text, button);
            });
        });
    }

    function init() {
        initMaps();
        initNativeShareButtons();
        initShareCopyButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
