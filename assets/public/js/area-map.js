(function () {
    'use strict';

    function getStrings() {
        var fallbackStrings = {
            mapUnavailable: 'Einsatzgebiet-Karte nicht verfügbar.',
            mapUnavailableHint: 'Bitte prüfen Sie die hinterlegten PLZ oder die Kartendaten.',
            callSingular: 'Einsatz',
            callPlural: 'Einsätze',
            streetsLabel: 'Straßen',
            moreStreetsLabel: 'weitere Straßen',
            stationLabel: 'Feuerwehrhaus'
        };

        if (window.feu_einsatz_area_map && window.feu_einsatz_area_map.strings) {
            return Object.assign({}, fallbackStrings, window.feu_einsatz_area_map.strings);
        }

        return fallbackStrings;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeNumber(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        var normalized = String(value).replace(',', '.');
        var parsed = parseFloat(normalized);

        return Number.isFinite(parsed) ? parsed : null;
    }

    function buildClusterTooltip(cluster, strings) {
        var streets = Array.isArray(cluster.streets) ? cluster.streets.filter(Boolean) : [];
        var visibleStreets = streets.slice(0, 8);
        var hiddenStreetCount = Math.max(0, (cluster.street_count || streets.length) - visibleStreets.length);
        var html = '<div class="feu-einsatz-area-tooltip">';

        html += '<strong>' + escapeHtml(String(cluster.count || 0) + ' ' + ((cluster.count || 0) === 1 ? strings.callSingular : strings.callPlural)) + '</strong>';

        if (visibleStreets.length) {
            html += '<div class="feu-einsatz-area-tooltip-label">' + escapeHtml(strings.streetsLabel) + '</div>';
            html += '<div class="feu-einsatz-area-tooltip-streets">' + visibleStreets.map(function (street) {
                return escapeHtml(street);
            }).join('<br>') + '</div>';
        }

        if (hiddenStreetCount > 0) {
            html += '<div class="feu-einsatz-area-tooltip-more">+' + escapeHtml(hiddenStreetCount + ' ' + strings.moreStreetsLabel) + '</div>';
        }

        html += '</div>';

        return html;
    }

    function showFallback(runtime) {
        var fallback = runtime.querySelector('.feu-einsatz-area-map-fallback');

        if (fallback) {
            fallback.hidden = false;
        }
    }

    function getClusterRadius(count) {
        return Math.max(160, Math.min(780, 160 + ((parseInt(count, 10) || 0) * 24)));
    }

    function addBaseLayer(map) {
        return window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            minZoom: 5,
            updateWhenIdle: true,
            keepBuffer: 2,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
    }

    function normalizeAreaCenter(area) {
        if (!area || typeof area !== 'object') {
            return { lat: null, lng: null };
        }

        if (area.center && typeof area.center === 'object') {
            if (area.center.lat !== undefined || area.center.lng !== undefined) {
                return {
                    lat: normalizeNumber(area.center.lat),
                    lng: normalizeNumber(area.center.lng)
                };
            }

            if (area.center.latitude !== undefined || area.center.longitude !== undefined) {
                return {
                    lat: normalizeNumber(area.center.latitude),
                    lng: normalizeNumber(area.center.longitude)
                };
            }

            if (Array.isArray(area.center) && area.center.length >= 2) {
                return {
                    lat: normalizeNumber(area.center[0]),
                    lng: normalizeNumber(area.center[1])
                };
            }
        }

        return {
            lat: normalizeNumber(area.latitude),
            lng: normalizeNumber(area.longitude)
        };
    }

    function normalizeAreaGeoJson(area) {
        if (!area || typeof area !== 'object') {
            return null;
        }

        var geojson = area.geojson || area.geometry || area.feature || null;

        if (typeof geojson === 'string' && geojson.trim() !== '') {
            try {
                geojson = JSON.parse(geojson);
            } catch (error) {
                geojson = null;
            }
        }

        if (geojson && typeof geojson === 'object' && geojson.geometry && typeof geojson.geometry === 'object' && geojson.geometry.type) {
            geojson = geojson.geometry;
        }

        if (!geojson || typeof geojson !== 'object' || !geojson.type) {
            return null;
        }

        if (['Point', 'MultiPoint'].indexOf(String(geojson.type)) !== -1) {
            return null;
        }

        return geojson;
    }

    function getAreaLayerStyle(geojson) {
        var type = geojson && geojson.type ? String(geojson.type) : '';

        if (type === 'LineString' || type === 'MultiLineString') {
            return {
                color: '#0a4b78',
                weight: 6,
                opacity: 0.96,
                lineCap: 'round',
                lineJoin: 'round'
            };
        }

        return {
            color: '#0a4b78',
            weight: 5,
            fillColor: '#0a4b78',
            fillOpacity: 0.34,
            opacity: 0.95
        };
    }

    function getAreaFillLayerStyle() {
        return {
            color: '#0a4b78',
            weight: 2,
            fillColor: '#0a4b78',
            fillOpacity: 0.20,
            opacity: 0.42
        };
    }

    function getAreaBoundsOverlayStyle(subtleOnly) {
        if (subtleOnly) {
            return {
                color: '#0a4b78',
                weight: 0,
                fillColor: '#0a4b78',
                fillOpacity: 0.12,
                opacity: 0
            };
        }

        return {
            color: '#0a4b78',
            weight: 1.5,
            fillColor: '#0a4b78',
            fillOpacity: 0.14,
            opacity: 0.22
        };
    }

    function isLineAreaGeoJson(geojson) {
        var type = geojson && geojson.type ? String(geojson.type) : '';

        return type === 'LineString' || type === 'MultiLineString';
    }

    function areGeoJsonCoordinatesClose(left, right) {
        if (!Array.isArray(left) || !Array.isArray(right) || left.length < 2 || right.length < 2) {
            return false;
        }

        var leftLng = normalizeNumber(left[0]);
        var leftLat = normalizeNumber(left[1]);
        var rightLng = normalizeNumber(right[0]);
        var rightLat = normalizeNumber(right[1]);

        if (leftLng === null || leftLat === null || rightLng === null || rightLat === null) {
            return false;
        }

        return Math.abs(leftLng - rightLng) <= 0.0002 && Math.abs(leftLat - rightLat) <= 0.0002;
    }

    function normalizeClosedRingCoordinates(coordinates) {
        if (!Array.isArray(coordinates) || coordinates.length < 3) {
            return null;
        }

        var ring = coordinates.slice();
        var first = ring[0];
        var last = ring[ring.length - 1];

        if (!areGeoJsonCoordinatesClose(first, last)) {
            ring.push(first);
        }

        return ring.length >= 4 ? ring : null;
    }

    function buildFillGeoJsonFromLine(geojson) {
        if (!geojson || typeof geojson !== 'object' || !geojson.type || !Array.isArray(geojson.coordinates)) {
            return null;
        }

        if (String(geojson.type) === 'LineString') {
            var ring = normalizeClosedRingCoordinates(geojson.coordinates);

            if (!ring) {
                return null;
            }

            return {
                type: 'Polygon',
                coordinates: [ring]
            };
        }

        if (String(geojson.type) === 'MultiLineString') {
            var polygons = [];

            geojson.coordinates.forEach(function (line) {
                var polygonRing = normalizeClosedRingCoordinates(line);

                if (polygonRing) {
                    polygons.push([polygonRing]);
                }
            });

            if (!polygons.length) {
                return null;
            }

            if (polygons.length === 1) {
                return {
                    type: 'Polygon',
                    coordinates: polygons[0]
                };
            }

            return {
                type: 'MultiPolygon',
                coordinates: polygons
            };
        }

        return null;
    }

    function normalizeAreaBounds(area) {
        if (!area || typeof area !== 'object') {
            return null;
        }

        var bounds = area.bounds || area.boundingbox || area.bbox || null;

        if (Array.isArray(bounds) && bounds.length >= 4) {
            var south = normalizeNumber(bounds[0]);
            var north = normalizeNumber(bounds[1]);
            var west = normalizeNumber(bounds[2]);
            var east = normalizeNumber(bounds[3]);

            if (south !== null && north !== null && west !== null && east !== null) {
                return {
                    south: Math.min(south, north),
                    north: Math.max(south, north),
                    west: Math.min(west, east),
                    east: Math.max(west, east)
                };
            }
        }

        if (bounds && typeof bounds === 'object') {
            var normalized = {
                south: normalizeNumber(bounds.south),
                north: normalizeNumber(bounds.north),
                west: normalizeNumber(bounds.west),
                east: normalizeNumber(bounds.east)
            };

            if (
                normalized.south !== null
                && normalized.north !== null
                && normalized.west !== null
                && normalized.east !== null
            ) {
                var southValue = Math.min(normalized.south, normalized.north);
                var northValue = Math.max(normalized.south, normalized.north);
                var westValue = Math.min(normalized.west, normalized.east);
                var eastValue = Math.max(normalized.west, normalized.east);

                return {
                    south: southValue,
                    north: northValue,
                    west: westValue,
                    east: eastValue
                };
            }
        }

        return null;
    }

    function addAreaCenterMarker(map, area, tooltipText, bounds) {
        var center = normalizeAreaCenter(area);

        if (center.lat === null || center.lng === null) {
            return;
        }

        var centerMarker = window.L.circleMarker([center.lat, center.lng], {
            radius: 7,
            color: '#ffffff',
            weight: 3,
            fillColor: '#0a4b78',
            fillOpacity: 1
        }).addTo(map);

        centerMarker.bindTooltip(tooltipText, {
            sticky: true,
            direction: 'top',
            opacity: 0.92
        });

        bounds.extend(centerMarker.getLatLng());
    }

    function normalizeStation(station) {
        if (!station || typeof station !== 'object') {
            return null;
        }

        var latitude = normalizeNumber(station.latitude);
        var longitude = normalizeNumber(station.longitude);

        if (latitude === null || longitude === null) {
            return null;
        }

        return {
            latitude: latitude,
            longitude: longitude,
            label: String(station.label || '').trim(),
            address: String(station.address || '').trim(),
            logoUrl: String(station.logo_url || station.logoUrl || '').trim(),
            logoSize: Math.max(20, Math.min(96, parseInt(station.logo_size || station.logoSize, 10) || 40))
        };
    }

    function addStationLayer(map, station, bounds, strings) {
        var normalizedStation = normalizeStation(station);

        if (!normalizedStation) {
            return 0;
        }

        var label = normalizedStation.label || strings.stationLabel;
        var popupHtml = '<div class="feu-einsatz-area-tooltip"><strong>' + escapeHtml(label) + '</strong>'
            + (normalizedStation.address ? '<div class="feu-einsatz-area-tooltip-streets">' + escapeHtml(normalizedStation.address) + '</div>' : '')
            + '</div>';
        var marker;
        var tooltipLabel = normalizedStation.address ? label + ': ' + normalizedStation.address : label;

        if (normalizedStation.logoUrl) {
            marker = window.L.marker([normalizedStation.latitude, normalizedStation.longitude], {
                icon: window.L.divIcon({
                    className: 'feu-einsatz-area-station-icon-shell',
                    html: '<span class="feu-einsatz-area-station-icon" style="width:' + normalizedStation.logoSize + 'px;height:' + normalizedStation.logoSize + 'px;"><img src="' + escapeHtml(normalizedStation.logoUrl) + '" alt="' + escapeHtml(label) + '" /></span>',
                    iconSize: [normalizedStation.logoSize, normalizedStation.logoSize],
                    iconAnchor: [normalizedStation.logoSize / 2, normalizedStation.logoSize / 2]
                })
            }).addTo(map);
        } else {
            marker = window.L.circleMarker([normalizedStation.latitude, normalizedStation.longitude], {
                radius: 10,
                color: '#ffffff',
                weight: 3,
                fillColor: '#c53030',
                fillOpacity: 1
            }).addTo(map);
        }

        marker.bindTooltip(tooltipLabel, {
            permanent: true,
            direction: 'right',
            offset: [12, 0],
            opacity: 0.96,
            className: 'feu-einsatz-area-station-tooltip'
        });
        marker.bindPopup(popupHtml, {
            maxWidth: 260
        });

        bounds.extend([normalizedStation.latitude, normalizedStation.longitude]);

        return 1;
    }

    function addAreaLayers(map, areas, bounds) {
        var addedLayers = 0;

        areas.forEach(function (area) {
            var tooltipText = 'PLZ ' + String(area.postcode || area.label || '').trim();
            var geojson = normalizeAreaGeoJson(area);
            var areaBounds = normalizeAreaBounds(area);
            var fillGeojson = null;
            var boundsOverlay = null;

            if (areaBounds) {
                bounds.extend([
                    [areaBounds.south, areaBounds.west],
                    [areaBounds.north, areaBounds.east]
                ]);
            }

            try {
                if (geojson) {
                    if (isLineAreaGeoJson(geojson)) {
                        fillGeojson = buildFillGeoJsonFromLine(geojson);
                    }

                    if (fillGeojson) {
                        var fillLayer = window.L.geoJSON(fillGeojson, {
                            style: function () {
                                return getAreaFillLayerStyle();
                            }
                        }).addTo(map);

                        if (fillLayer.getBounds && fillLayer.getBounds().isValid()) {
                            bounds.extend(fillLayer.getBounds());
                        }
                    } else if (areaBounds) {
                        boundsOverlay = window.L.rectangle([
                            [areaBounds.south, areaBounds.west],
                            [areaBounds.north, areaBounds.east]
                        ], getAreaBoundsOverlayStyle(true)).addTo(map);
                    }

                    var geoLayer = window.L.geoJSON(geojson, {
                        style: function () {
                            return getAreaLayerStyle(geojson);
                        }
                    }).addTo(map);

                    geoLayer.bindTooltip(tooltipText, {
                        sticky: true,
                        direction: 'top',
                        opacity: 0.92
                    });

                    if (geoLayer.getBounds && geoLayer.getBounds().isValid()) {
                        bounds.extend(geoLayer.getBounds());
                    }

                    if (boundsOverlay) {
                        bounds.extend(boundsOverlay.getBounds());
                    }

                    addAreaCenterMarker(map, area, tooltipText, bounds);
                    addedLayers += fillGeojson ? 2 : (boundsOverlay ? 2 : 1);
                    return;
                }
            } catch (error) {
                // Continue with rectangle/circle fallback.
            }

            if (areaBounds) {
                var rectangle = window.L.rectangle([
                    [areaBounds.south, areaBounds.west],
                    [areaBounds.north, areaBounds.east]
                ], getAreaBoundsOverlayStyle(false)).addTo(map);

                rectangle.bindTooltip(tooltipText, {
                    sticky: true,
                    direction: 'top',
                    opacity: 0.92
                });

                bounds.extend(rectangle.getBounds());
                addAreaCenterMarker(map, area, tooltipText, bounds);
                addedLayers++;
                return;
            }

            var center = normalizeAreaCenter(area);

            if (center.lat === null || center.lng === null) {
                return;
            }

            var circle = window.L.circle([center.lat, center.lng], {
                radius: 3200,
                color: '#0a4b78',
                weight: 4,
                fillColor: '#0a4b78',
                fillOpacity: 0.24,
                dashArray: '10 6'
            }).addTo(map);

            circle.bindTooltip(tooltipText, {
                sticky: true,
                direction: 'top',
                opacity: 0.92
            });

            bounds.extend(circle.getBounds());
            addAreaCenterMarker(map, area, tooltipText, bounds);
            addedLayers++;
        });

        return addedLayers;
    }

    function addClusterLayers(map, clusters, bounds, strings) {
        var addedLayers = 0;

        clusters.forEach(function (cluster) {
            var lat = normalizeNumber(cluster.latitude);
            var lng = normalizeNumber(cluster.longitude);

            if (lat === null || lng === null) {
                return;
            }

            try {
                var circle = window.L.circle([lat, lng], {
                    radius: getClusterRadius(cluster.count),
                    color: '#c53030',
                    weight: 3,
                    fillColor: '#c53030',
                    fillOpacity: 0.22
                }).addTo(map);

                circle.bindTooltip(buildClusterTooltip(cluster, strings), {
                    sticky: true,
                    direction: 'top',
                    opacity: 0.96
                });

                bounds.extend(circle.getBounds());
                addedLayers++;
            } catch (error) {
                // Ignore malformed cluster entries and continue rendering the rest.
            }
        });

        return addedLayers;
    }

    function renderAreaMap(runtime) {
        if (!window.L) {
            showFallback(runtime);
            return;
        }

        var canvas = runtime.querySelector('.feu-einsatz-area-map-canvas');
        var configElement = runtime.querySelector('.feu-einsatz-area-map-config');

        if (!canvas || !configElement) {
            showFallback(runtime);
            return;
        }

        var config = {};

        try {
            config = JSON.parse(configElement.textContent || '{}');
        } catch (error) {
            showFallback(runtime);
            return;
        }

        var areas = Array.isArray(config.areas) ? config.areas : [];
        var clusters = Array.isArray(config.clusters) ? config.clusters : [];
        var station = config.station && typeof config.station === 'object' ? config.station : null;

        if (!areas.length && !clusters.length && !station) {
            showFallback(runtime);
            return;
        }

        var map = window.L.map(canvas, {
            attributionControl: true,
            zoomControl: true,
            scrollWheelZoom: false,
            dragging: true,
            touchZoom: true,
            doubleClickZoom: true,
            keyboard: true,
            preferCanvas: true
        });

        addBaseLayer(map);

        var bounds = window.L.latLngBounds([]);
        var strings = getStrings();
        var renderedLayers = 0;

        renderedLayers += addAreaLayers(map, areas, bounds);

        if (config.showCalls) {
            renderedLayers += addClusterLayers(map, clusters, bounds, strings);
        }

        renderedLayers += addStationLayer(map, station, bounds, strings);

        if (renderedLayers > 0 && bounds.isValid()) {
            map.fitBounds(bounds.pad(0.12), {
                maxZoom: 15
            });
        } else {
            showFallback(runtime);
            return;
        }

        window.setTimeout(function () {
            map.invalidateSize();
        }, 120);
    }

    function initAreaMaps() {
        var runtimes = document.querySelectorAll('.feu-einsatz-area-map-runtime[data-area-map-enabled="1"]');

        runtimes.forEach(function (runtime) {
            try {
                renderAreaMap(runtime);
            } catch (error) {
                showFallback(runtime);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAreaMaps);
    } else {
        initAreaMaps();
    }
})();
