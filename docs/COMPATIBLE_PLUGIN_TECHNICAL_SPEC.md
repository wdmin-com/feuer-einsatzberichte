# Техническое задание: совместимый плагин Einsatzberichte

## 1. Цель и уровень совместимости

Разработать WordPress-плагин для ведения пожарных вызовов (Einsatzberichte). Новый плагин может иметь иной frontend, CSS, компоненты админ-панели и библиотеку диаграмм, но обязан читать и записывать данные в том же формате, что текущий плагин Feuer Einsatzberichte.

Совместимая реализация обязана:

- использовать обычные WordPress-записи post, а не новый custom post type;
- сохранять имена таблиц, options, user meta, post meta, shortcodes, hooks и URL-actions из этого документа;
- открывать и без потерь пересохранять отчёты, созданные старым плагином;
- поддерживать PHP 8.1+ и WordPress 7.1+;
- не делать дизайн частью контракта: UI можно заменить полностью, технический контракт менять нельзя.

Внутренние private-методы и CSS-классы не являются контрактом. Внешние результаты, данные и интеграционные точки — являются.

## 2. Термины

| Термин | Значение |
|---|---|
| Einsatzbericht | Отчёт о выезде. Обычная запись WordPress post. |
| Einsatzstichwort | Категория/код вызова: FEU, THY, WASSER и т. п. |
| Teilnehmer | Участник/член команды в отдельной таблице. |
| Organisation | Организация, участвовавшая в вызове. |
| Kartenbild | Автоматически сгенерированная PNG-карта. |
| Straßen-Geometrie | Набор сегментов настоящей улицы, а не прямая линия. |

## 3. Активация и первичная настройка

### 3.1 Активация

При активации:

1. Проверить PHP >= 8.1 и WordPress >= 7.1. При несоответствии деактивировать плагин и показать причину.
2. Через dbDelta создать/обновить таблицы из раздела 5 с текущим $wpdb->prefix и charset/collation WordPress.
3. Добавить только отсутствующие options из раздела 6; существующие значения не перезаписывать.
4. Создать корень категорий Einsätze и выбранные дочерние Kategorien из раздела 4, записать IDs в feu_einsatz_categories.
5. Создать upload-каталог архивов, добавить стандартные организации, выполнить безопасные legacy migrations.
6. Обновить feu_einsatz_schema_version и feu_einsatz_version, вызвать flush_rewrite_rules().
7. Запустить мастер через feu_einsatz_setup_wizard_pending = 1.

Деактивация ничего не удаляет: ни записи, ни таблицы, ни media, ни настройки.

### 3.2 Ersteinrichtung

Мастер показывается только администраторам, пока feu_einsatz_setup_wizard_pending = 1. После успешного завершения он не должен появляться во всех разделах. Окно обязано иметь Отложить и Закрыть.

Шаги:

1. Проверить/установить категории.
2. Ввести Feuerwehrhaus-Adresse: Straße, PLZ, Stadt — обязательны; logo необязательно.
3. Выбрать хотя бы одно Einsatzstichwort.
4. Вопрос: создать примерный Einsatzbericht? Да/нет и адрес примера.
5. Вопрос: создать примерного Teilnehmer? Да/нет.
6. Итоговая проверка и сохранение.

Отложить сохраняет feu_einsatz_setup_wizard_snoozed_until. Закрытие не завершает мастер. Формы работают без JavaScript через обычный POST, имеют nonce, серверную валидацию и понятные ошибки.

## 4. Категории WordPress

Taxonomy всегда category.

Корень:
- name: Einsätze
- slug: einsatze
- description: Einsatzstichworte der Feuerwehr

Дочерние категории создаются под этим корнем. Название равно коду, slug = sanitize_title(кода).

| Код | Description |
|---|---|
| ALARM | Alarm Meldung |
| DRZF | Droht zu fallen (z.B. Baum) |
| FEU | Feuer |
| FEU2-5 | Alarmstufenerhöhungen FEUER (2-5 HLZ + Sonderkomponenten) |
| FEUAUS | Feuer bereits gelöscht / Überprüfung |
| FEUBAB | Feuer auf der Autobahn |
| FEUBMA | Feuermeldung durch Brandmeldeanlage |
| FEUFLUG | Feuer an/im Flugzeug |
| FEUK | Kleinalarm (Nur 1 HLF oder 1 FF, z.B. Müllcontainer, Pkw) |
| FEUMANV | Feuer mit einem Massenanfall von Verletzten (Großschadenlage) (ab fünf Verletzten) |
| FEUTU | Feuer im Tunnel |
| FEUWA | Feuer auf dem Wasser |
| FEUX | Mit Gefahrstoffen |
| FEUY | Menschenleben in Gefahr |
| FEUZUG | Feuer am/im Zug |
| KMF | Kampfmittelfund |
| NIL | Nicht in Liste erfasste Schadenslage |
| NOTFAF | Notfall |
| PSCHL | Person eingeschlossen (z.B. im Aufzug) |
| TH | Hilfeleistungseinsatz |
| TH2-5 | Alarmstufenerhöhungen HILFE (2-5 HLZ + Sonderkomponenten) |
| THBAB | Hilfeleistung auf der Autobahn |
| THFLUG | Hilfeleistung an/im Flugzeug (u.a. Notlandung) |
| THK | Kleinalarm (z.B. Liegenbleiber, Verkehrshindernisse) |
| THMANV | Technische Hilfeleistung mit einem Massenanfall von Verletzten (ab fünf Verletzten) |
| THTU | Hilfeleistung im Tunnel |
| THV | Technische Hilfeleistung Einsturz / Verschüttung |
| THWA | Hilfeleistung auf dem Wasser |
| THX | Mit Gefahrstoffen |
| THY | Menschenleben in Gefahr (z.B. Verkehrsunfall) |
| THYHOE | Technische Hilfeleistung mit Menschenleben in Gefahr in großer Höhe |
| THZUG | Hilfeleistung am/im Zug |
| TIER | Tier in Notlage |
| TV | Tür verschlossen (wenn Notfall vermutet wird: TVNOT) |
| WASSER | Wasserschaden |

Если term уже существует, использовать его и при необходимости переместить под Einsätze. Дубликаты не создавать.

## 5. Таблицы базы данных

Во всех именах вместо prefix используется реальный $wpdb->prefix. Например, при wp_ это wp_feu_einsatz_teilnehmer. Имена не изменять.

### feu_einsatz_teilnehmer

    id int(11) PK AUTO_INCREMENT
    vorname varchar(100) NOT NULL
    nachname varchar(100) NOT NULL
    job_title varchar(150) DEFAULT ''
    entry_date varchar(20) DEFAULT ''
    rank_title varchar(150) DEFAULT ''
    member_function varchar(150) DEFAULT ''
    education text NULL
    description longtext NULL
    sort_order int(11) DEFAULT 0
    category_ids longtext NULL
    gallery_ids longtext NULL
    primary_image_id int(11) DEFAULT 0
    default_functions longtext NULL
    is_archived tinyint(1) DEFAULT 0
    is_deleted tinyint(1) DEFAULT 0
    created_at datetime DEFAULT CURRENT_TIMESTAMP
    KEY archived_order (is_archived, sort_order)

category_ids, gallery_ids и default_functions — WordPress-совместимые сериализованные массивы. Архивного участника не удалять: is_archived=1 исключает из обычного выбора. is_deleted=1 — soft-delete/legacy compatibility.

### feu_einsatz_statistiken

    id int(11) PK AUTO_INCREMENT
    post_id int(11) NOT NULL
    teilnehmer_id int(11) NOT NULL
    funktion varchar(100) NOT NULL
    created_at datetime DEFAULT CURRENT_TIMESTAMP
    UNIQUE KEY unique_einsatz_teilnehmer (post_id, teilnehmer_id)
    KEY post_id (post_id)
    KEY teilnehmer_id (teilnehmer_id)

Одна строка — один участник в одном отчёте. При сохранении отчёта синхронизировать её с _feu_einsatz_teilnehmer.

### feu_einsatz_organisationen

    id int(11) PK AUTO_INCREMENT
    name varchar(100) NOT NULL
    color varchar(7) DEFAULT '#0a4b78'
    post_link varchar(255) DEFAULT ''
    is_archived tinyint(1) DEFAULT 0
    sort_order int(11) DEFAULT 0
    created_at datetime DEFAULT CURRENT_TIMESTAMP
    KEY archived_name (is_archived, name)
    KEY archived_order (is_archived, sort_order)

### feu_einsatz_street_registry

    id int(11) PK AUTO_INCREMENT
    street varchar(191) NOT NULL
    postcode varchar(5) DEFAULT ''
    city varchar(120) DEFAULT 'Hamburg'
    sort_order int(11) DEFAULT 0
    created_at datetime DEFAULT CURRENT_TIMESTAMP
    UNIQUE KEY street_lookup (street, postcode, city)
    KEY street_order (sort_order, street)

### feu_einsatz_statistics_cache

    id int(11) PK AUTO_INCREMENT
    teilnehmer_id int(11) NOT NULL
    jahr int(4) NOT NULL
    maschinist int(11) DEFAULT 0
    gruppenfuhrer int(11) DEFAULT 0
    pa_tf_traeger1 int(11) DEFAULT 0
    pa_traeger2 int(11) DEFAULT 0
    melder int(11) DEFAULT 0
    wasser1 int(11) DEFAULT 0
    wasser2 int(11) DEFAULT 0
    schlauch1 int(11) DEFAULT 0
    schlauch2 int(11) DEFAULT 0
    keine_funktion int(11) DEFAULT 0
    gesamt_einsaetze int(11) DEFAULT 0
    letztes_update datetime DEFAULT CURRENT_TIMESTAMP
    UNIQUE KEY unique_teilnehmer_jahr (teilnehmer_id, jahr)
    KEY teilnehmer_id (teilnehmer_id)
    KEY jahr (jahr)

Это legacy cache. Новые функции считаются из feu_einsatz_statistiken, не из устаревших колонок.

### feu_einsatz_archive

    id bigint(20) unsigned PK AUTO_INCREMENT
    archive_key varchar(64) UNIQUE NOT NULL
    filename varchar(255) NOT NULL
    label varchar(255) DEFAULT ''
    file_size bigint(20) unsigned DEFAULT 0
    created_by bigint(20) unsigned DEFAULT 0
    created_by_name varchar(191) DEFAULT ''
    source varchar(40) DEFAULT 'created'
    notes longtext NULL
    manifest longtext NULL
    created_at datetime DEFAULT CURRENT_TIMESTAMP
    restored_at datetime NULL
    KEY created_at (created_at)

### feu_einsatz_logs

    id bigint(20) unsigned PK AUTO_INCREMENT
    user_id bigint(20) unsigned DEFAULT 0
    user_name varchar(191) DEFAULT ''
    action_type varchar(80) NOT NULL
    entity_type varchar(80) DEFAULT ''
    entity_id bigint(20) unsigned DEFAULT 0
    message text NULL
    details longtext NULL
    page_slug varchar(191) DEFAULT ''
    page_url text NULL
    ip_address varchar(64) DEFAULT ''
    created_at datetime DEFAULT CURRENT_TIMESTAMP
    KEY action_type (action_type)
    KEY created_at (created_at)

## 6. Options contract

Массивы сохраняются WordPress Options API (serialized arrays), не самодельным JSON.

### Базовые

| Key | Default |
|---|---|
| feu_einsatz_version | версия установленного плагина |
| feu_einsatz_schema_version | версия схемы |
| feu_einsatz_update_manifest_url | URL update manifest |
| feu_einsatz_functions | Maschinist, Gruppenführer, ATF, ATM, Melder, WTF, WTM, STF, STM, Mannschaft |
| feu_einsatz_default_participant_function | Mannschaft |
| feu_einsatz_categories | массив term IDs |
| feu_einsatz_default_categories_prompt | 0 после установки |
| feu_einsatz_setup_wizard_pending | 1 на чистой установке |
| feu_einsatz_setup_wizard_snoozed_until | отсутствует до откладывания |
| feu_einsatz_role_access | [] или матрица ролей |
| feu_einsatz_feature_organizations_enabled | 1 |

### Карта и Feuerwehrhaus

| Key | Default |
|---|---|
| feu_einsatz_map_zoom | 16 |
| feu_einsatz_map_height | 400 |
| feu_einsatz_auto_map_image | 1 |
| feu_einsatz_map_preview_heading_text | '' |
| feu_einsatz_map_preview_show_panel | 1 |
| feu_einsatz_map_preview_show_panel_heading | 1 |
| feu_einsatz_map_preview_show_panel_address | 1 |
| feu_einsatz_map_preview_panel_position | bottom-left |
| feu_einsatz_map_preview_show_street_label | 1 |
| feu_einsatz_map_preview_street_label_prefix | Einsatz Straße |
| feu_einsatz_map_preview_street_label_position | auto |
| feu_einsatz_map_preview_show_attribution | 1 |
| feu_einsatz_map_preview_attribution_text | Leaflet \| © OpenStreetMap contributors |
| feu_einsatz_map_preview_attribution_position | bottom-right |
| feu_einsatz_map_label_style | bubble |
| feu_einsatz_map_label_text_color | #ffffff |
| feu_einsatz_map_preview_highlight_color | #d92d20 |
| feu_einsatz_map_preview_stroke_width | 8 |
| feu_einsatz_map_preview_font_family | auto |
| feu_einsatz_area_page_enabled | 0 |
| feu_einsatz_area_show_calls | 1 |
| feu_einsatz_area_postcodes | [] |
| feu_einsatz_area_station_street | '' |
| feu_einsatz_area_station_postcode | '' |
| feu_einsatz_area_station_city | Hamburg |
| feu_einsatz_area_station_logo_id | 0 |
| feu_einsatz_area_station_logo_size | 40 |

### Вывод, social и media

| Key | Default |
|---|---|
| feu_einsatz_default_comments_enabled | 0 |
| feu_einsatz_default_card_variant | modern |
| feu_einsatz_related_reports_display | cards |
| feu_einsatz_related_reports_count | 6 |
| feu_einsatz_single_desaturate_organizations | 0 |
| feu_einsatz_single_info_fields | street, location, date, time, category, organizations |
| feu_einsatz_single_map_display_mode | live; допустимы live, image, disabled |
| feu_einsatz_single_map_privacy_mode | always; допустимы always, consent_hide, consent_image |
| feu_einsatz_single_live_map_show_station | 0 |
| feu_einsatz_overview_show_stats | 1 |
| feu_einsatz_overview_show_year_filter | 1 |
| feu_einsatz_social_share_enabled_networks | share, facebook, instagram, whatsapp, telegram |
| feu_einsatz_social_share_image_mode | post_image; допустимы post_image, generated |
| feu_einsatz_social_share_background_id | 0 |
| feu_einsatz_social_share_fields | title, date, time, number, description, url |
| feu_einsatz_social_share_layout | wide; wide 1200×630, story 1080×1920, feed 1080×1350 |
| feu_einsatz_social_share_logo_id | 0 |
| feu_einsatz_social_share_badge_text | PRESSEMITTEILUNG |
| feu_einsatz_social_share_cta_text | Weitere Infos |
| feu_einsatz_social_share_title_color | #ffffff |
| feu_einsatz_social_share_description_color | #dbeafe |
| feu_einsatz_social_share_panel_color | #0f2f5f |
| feu_einsatz_social_share_accent_color | #ef233c |
| feu_einsatz_social_share_cta_fill_color | #ffffff |
| feu_einsatz_social_share_cta_text_color | #0f2f5f |
| feu_einsatz_social_share_title_scale | 118 |
| feu_einsatz_social_share_description_scale | 112 |
| feu_einsatz_social_share_description_max_lines | 5 |
| feu_einsatz_social_share_logo_scale | 100 |
| feu_einsatz_social_share_logo_width | 220 |
| feu_einsatz_social_share_overlay_enabled | 1 |
| feu_einsatz_social_share_image_blur | 0 |
| feu_einsatz_social_share_panel_radius | 30 |
| feu_einsatz_social_share_badge_radius | 40 |
| feu_einsatz_social_share_link_radius | 14 |
| feu_einsatz_social_share_text_align | auto |
| feu_einsatz_social_share_logo_position | bottom-right |
| feu_einsatz_social_meta_enabled | 1 |
| feu_einsatz_social_meta_canonical_enabled | 1 |
| feu_einsatz_social_meta_schema_enabled | 1 |
| feu_einsatz_social_meta_twitter_site | '' |
| feu_einsatz_backup_retention_limit | 5 |
| feu_einsatz_photo_watermark_enabled | 1 |
| feu_einsatz_photo_watermark_text | get_bloginfo('name') |
| feu_einsatz_photo_watermark_image_id | 0 |
| feu_einsatz_photo_watermark_opacity | 36 |
| feu_einsatz_photo_watermark_scale | 42 |

Дополнительно: feu_einsatz_settings_history хранит максимум 10 snapshots настроек.

## 7. Отчёт и post meta

Отчёт — wp_posts.post_type = post с обязательным маркером:

    _feu_einsatz_einsatzbericht = '1'

Legacy reader может дополнительно распознавать отчёт по мета/дочерней категории Einsätze, но новое сохранение всегда пишет этот marker.

| Post meta | Формат | Назначение |
|---|---|---|
| _feu_einsatz_strasse | string | Улица |
| _feu_einsatz_hausnummer | string | Номер дома |
| _feu_einsatz_plz | string | PLZ |
| _feu_einsatz_stadt | string | Город, default Hamburg |
| _feu_einsatz_datum | Y-m-d | Дата вызова |
| _feu_einsatz_uhrzeit | H:i | Время вызова |
| _feu_einsatz_latitude | decimal string | Latitude |
| _feu_einsatz_longitude | decimal string | Longitude |
| _feu_einsatz_display_address | string | Адрес геокодера |
| _feu_einsatz_teilnehmer | serialized IDs | Участники |
| _feu_einsatz_organisationen | serialized IDs | Организации |
| _feu_einsatz_gallery | serialized attachment IDs | Галерея |
| _feu_einsatz_comments_enabled | '1' или '0' | Комментарии |
| _feu_einsatz_availability_mode | sofort, date, plus2 | Правило публикации |
| _feu_einsatz_available_from | Y-m-d H:i:s | Время доступности |
| _feu_einsatz_demo_record | '1' | Демоданные |
| _feu_einsatz_setup_sample | '1' | Пример мастера |

date публикует в рассчитанную дату/время, plus2 — не ранее 48 часов, sofort — сразу. Валидация обязательных полей выполняется сервером до изменения записи.

### Геометрия улицы

Актуальные ключи:

    _feu_einsatz_street_geometry_final
    _feu_einsatz_street_center_final
    _feu_einsatz_street_cache_version = '13'
    _feu_einsatz_street_cache_revision

Geometry — serialized array сегментов:

    [
      [
        'points' => [ ['lat'=>53.55, 'lng'=>9.99], ... ],
        'highway' => 'residential',
        'kind' => 'road' // либо pedestrian
      ]
    ]

Минимум две точки в сегменте. При чтении также принимать точки [lat,lng], lat/lon, latitude/longitude и legacy keys:

    _feu_einsatz_street_geometry
    _feu_einsatz_street_center
    _feu_einsatz_street_bounds
    _feu_einsatz_street_line_data

### Kartenbild meta

    _feu_einsatz_generated_map_thumbnail_id
    _feu_einsatz_generated_map_preview
    _feu_einsatz_generated_map_preview_url
    _feu_einsatz_generated_map_preview_file
    _feu_einsatz_generated_map_signature
    _feu_einsatz_generated_map_queue
    _feu_einsatz_generated_map_status
    _feu_einsatz_generated_map_publish_hold

Generated map используется как fallback thumbnail, если нет featured image. Удалять только автоматически созданное map-media, не пользовательские вложения.

## 8. Карты, геокодирование и cache

1. Использовать Leaflet/OpenStreetMap для live-карты, Nominatim или совместимый сервис для адреса, Overpass или совместимый источник для улицы.
2. При доступной geometry выделять настоящую полилинию улицы цветом feu_einsatz_map_preview_highlight_color и толщиной feu_einsatz_map_preview_stroke_width. Нельзя заменять её прямой линией.
3. Прямая линия допустима лишь как последний технический fallback; это надо логировать.
4. Редактор, Live-Vorschau и публичная карта используют одну нормализацию geometry и показывают одинаковую улицу.
5. Cache version = 13. Transient key: feu_einsatz_street_geometry_ + md5('13|normalized-street|plz|city'). Список ключей в feu_einsatz_street_cache_keys.
6. Нормализация: lowercase, straße→strasse, ä→ae, ö→oe, ü→ue; пустой city = hamburg.
7. Post cache и transient обязаны очищаться при изменении адреса/координат, геометрии, цвета/толщины карты, restore и удалении отчёта.
8. Не выполнять remote HTTP на каждом page view; background retries через WP-Cron, timeout, rate-limit, backoff.
9. Privacy modes: always, consent_hide, consent_image. Display modes: live, image, disabled.

## 9. Пользовательские функции

### Админ-меню

Parent slug: feuer-einsatzberichte

Реальные совместимые slugs:

| Slug | Назначение |
|---|---|
| feuer-einsatzberichte | dashboard |
| edit.php?post_type=post&feu_einsatz_filter=1 | список отчётов |
| feu-einsatz-neuer-bericht | новый отчёт |
| feu-einsatz-schnelleingabe | быстрый ввод |
| feu-einsatz-bericht-bearbeiten | редактирование |
| feu-einsatz-teilnehmer | участники |
| feu-einsatz-statistiken | статистика |
| feu-einsatz-einstellungen | настройки |
| feu-einsatz-archive | архивы |
| feu-einsatz-logs | логи |

Tabs settings: allgemein, zugriff, medien, sozial, funktionen, organisationen, kategorien, karten, strassenregister, manifest, shortcodes, daten. Daten dauerhaft löschen — только tab daten.

### Отчёты, участники и организации

Форма отчёта поддерживает title, content, featured image, gallery, категории, адрес, дату/время, участников/функции, организации, комментарии, schedule, геокодирование, map preview, генерацию и удаление Kartenbild.

Save должен работать без JavaScript через admin-post.php. AJAX — только улучшение. После успешного POST выводится подтверждение; при ошибке — сохранённые поля и список ошибок.

Участники: CRUD, sort, archive, soft delete, image/gallery, default functions, description. Организации: CRUD, color #RRGGBB, link, ordering, archive. Если feu_einsatz_feature_organizations_enabled = 0, данные не удаляются — selector и frontend block скрываются.

### Статистика

Показать итоги года, сравнение лет, месяцы/дни, Einsatzstichworte, организации, участников, функции, ranking и activity map. Любая библиотека графиков допустима; обязателен таблицовый/текстовый fallback, keyboard accessibility и prefers-reduced-motion.

Годовой dashboard cache:
- transient feu_einsatz_statistics_dashboard_{year};
- TTL 3600;
- index option feu_einsatz_statistics_dashboard_cache_keys.

Cache инвалидируется после create/update/delete отчёта, изменения участников, restore backup и purge.

Статистика также включает полноэкранную Präsentation и PDF download. Презентация содержит включаемые секции welcome, overview, categories, calendar, activity_map, participants и собственные страницы. Сохранить следующие options и значения: feu_einsatz_statistics_presentation_sections (массив включённых встроенных секций), feu_einsatz_statistics_presentation_custom_pages (массив объектов title, subtitle, content, enabled, order), feu_einsatz_statistics_presentation_logo_size (default 72, допустимо 20..140), feu_einsatz_statistics_presentation_background_image_id (default 0), feu_einsatz_statistics_presentation_background_opacity (default 28, 0..100), feu_einsatz_statistics_presentation_background_darkness (default 18, 0..100), feu_einsatz_statistics_presentation_background_pages (массив section keys и custom_{index}). PDF может генерироваться другой библиотекой, но обязан содержать тот же выбранный год и доступные статистические данные.

### Frontend

Нужны single report, overview с year filter/pagination, lists, alarm list, optional area/Feuerwehrhaus page, комментарии, fallback Kartenbild и Open Graph/canonical/JSON-LD по настройкам.

Оставить зарегистрированным page-template key feuer-einsatzberichte-uebersicht-bootstrap.php с label Einsatz Uebersicht (Bootstrap), даже если разметка заменена.

## 10. Shortcodes

| Shortcode | Поведение |
|---|---|
| [feu_einsatz_anzahl zeitraum="aktuell"] | Число отчётов; поддержка aktuell, vorjahr, previous, last. |
| [feu_einsatz_aktuell] | Текущий год. |
| [feu_einsatz_vorjahr] | Прошлый год. |
| [feu_einsatz_seite] | Overview; jahr, posts_per_page, card_variant, card_style, kartenstil. |
| [feu_einsatz_liste] | Список/карточки. |
| [feu_einsatz_sidebar] | Компактный список. |
| [feu_einsatz_letzte] | Последние; anzahl, format, jahr и card attrs. |
| [feu_einsatz_alarm_liste] | Alarm list; anzahl, jahr, anzeigen, beschreibung, uebertitel, titel, alle_text, alle_url. |
| [feu_einsatz_gebiet] | Зона выезда. |

Shortcode всегда возвращает строку, экранирует output и не делает удалённый HTTP-запрос во время frontend render.

## 11. Integration API

### AJAX actions

Все требуют nonce feu_einsatz_ajax_nonce, capability check и JSON success/data или error message:

    feu_einsatz_search_streets
    feu_einsatz_get_street_registry
    feu_einsatz_save_street_registry_entry
    feu_einsatz_delete_street_registry_entry
    feu_einsatz_get_participant_details
    feu_einsatz_get_statistics
    feu_einsatz_save_participant
    feu_einsatz_toggle_participant_archive
    feu_einsatz_delete_participant
    feu_einsatz_save_organization
    feu_einsatz_sort_organizations
    feu_einsatz_toggle_organization_archive
    feu_einsatz_delete_organization
    feu_einsatz_generate_map_image

### admin-post actions

    feu_einsatz_complete_setup
    feu_einsatz_create_report
    feu_einsatz_update_report
    feu_einsatz_generate_map_image_now
    feu_einsatz_delete_map_image
    feu_einsatz_schnelleingabe
    feu_einsatz_create_archive
    feu_einsatz_upload_archive
    feu_einsatz_restore_archive
    feu_einsatz_delete_archive
    feu_einsatz_download_archive
    feu_einsatz_share_image
    feu_einsatz_share_image_public

Public share image требует HMAC SHA-256 signature. Payload: feu_einsatz_share_image_public|{post_id}|{expires_at}; key = AUTH_KEY, fallback wp_salt('auth'). Filter feu_einsatz_share_capability имеет default manage_options.

### Cron hooks

    feu_einsatz_generate_map_preview_background($post_id)
    feu_einsatz_background_geocode($post_id)
    feu_einsatz_prime_watermark_cache($attachment_ids, $queue_key)
    feu_einsatz_generate_share_card_background($post_id)

Задачи идемпотентны: повтор не создаёт duplicate media и не повреждает запись.

Поддержать hooks WordPress: theme_page_templates, template_include, comments_open, preprocess_comment, has_post_thumbnail, post_thumbnail_html, post_thumbnail_url, wp_enqueue_scripts, wp_head, pre_get_posts, save_post.

## 12. Backup, restore и полное удаление

Backup ZIP хранится в uploads, регистрируется в feu_einsatz_archive и содержит manifest, выбранные reports/meta/categories, participants, organisations, statistics, options, media/maps, comments и logs.

Restore проверяет ZIP/manifest до записи, импортирует батчами, сопоставляет новые IDs posts/terms/attachments, обновляет restored_at, создаёт audit log, очищает maps/statistics cache и не исполняет содержимое архива как PHP.

Выбор полного удаления строго ограничен разделами:

    participants, reports, statistics, settings, logs, archives

Удаляется только отмеченное. Перед удалением:
- current user должен иметь manage_options;
- создать случайный код 1000000..9999999 через random_int;
- сохранить password-hash в transient feu_einsatz_factory_reset_{user_id} на 10 минут;
- последний hash хранить в user meta feu_einsatz_last_factory_reset_code_hash;
- код одноразовый и удаляется после проверки;
- интерфейс предупреждает о необратимости.

После purge создаётся log с пользователем, секциями и количествами. При reports удалять assignments и auto-generated map assets, но не пользовательские изображения.

## 13. Права, защита и журнал

- Администратор с manage_options всегда имеет полный доступ.
- feu_einsatz_role_access: роли по dashboard, reports, create_report, quick_entry, participants, statistics, settings, archives, logs. edit_report — скрытый alias create_report.
- Для POST/AJAX: current_user_can, nonce, server validation, sanitizing, $wpdb->prepare, contextual escaping.
- Внешние HTTP: HTTPS, timeout, WP_Error, rate-limit/backoff.
- Не сохранять в logs reset codes, пароли, nonce, cookie и authorization headers.
- Логировать изменение настроек, удаление, restore, создание/изменение отчёта, ошибки map/background processing.

## 14. Функциональные модули

Новая архитектура допускается, но должны существовать эквивалентные модули:

| Модуль | Ответственность |
|---|---|
| FEU_Einsatz_Installer | activation, schema, defaults, categories, migrations |
| FEU_Einsatz_Database | participants, organisations, statistics, cache invalidation |
| FEU_Einsatz_Admin | admin pages, reports, settings, maps, setup, purge |
| FEU_Einsatz_Public | frontend, templates, shortcodes, meta tags, comments |
| FEU_Einsatz_Ajax_Handler | AJAX endpoints |
| FEU_Einsatz_Street_Cache | cache v13 и geometry normalisation |
| FEU_Einsatz_Template_Helpers | report detection/render/defaults |
| FEU_Einsatz_Backup_Manager | archives |
| FEU_Einsatz_Report_Share | signed share cards |
| FEU_Einsatz_Image_Protection | watermark |
| FEU_Einsatz_Participant_Ranking | ranking |
| FEU_Einsatz_Schnelleingabe | quick entry |
| FEU_Einsatz_Logger / FEU_Einsatz_Report_Logger | audit logging |
| FEU_Einsatz_Updater | update manifest |

## 15. Критерии приёмки

1. Активация новой версии на копии существующей БД проходит без PHP fatal/warnings.
2. Старый отчёт открывается, сохраняется и не теряет meta/participants/categories/maps.
3. Новый отчёт — обычный post с _feu_einsatz_einsatzbericht='1'.
4. Карта в editor, Live-Vorschau и single page показывает одну и ту же реальную улицу; при geometry нет прямой полосы.
5. Отчёт без featured image показывает Kartenbild и его можно массово сгенерировать из dashboard.
6. Все shortcodes возвращают корректный output на пустых и legacy data.
7. Backup create/upload/download/restore проверены на staging.
8. Factory reset: неверный/просроченный/использованный код отклоняется; удаляются только выбранные разделы.
9. Проверены роли, CSRF, XSS, SQL injection и запрет неавторизованного restore/delete.
10. В CI: PHP lint на 8.1 и актуальной PHP, WordPress activation smoke test, create/save report integration test, map geometry test, restore smoke test.

## 16. Жёсткие запреты для совместимой замены

Не изменять без обратимой миграции:

- семь имён custom tables;
- все feu_einsatz_* options, _feu_einsatz_* post meta, user meta и transient keys из этого документа;
- использование post вместо собственного post type;
- категории/иерархию из раздела 4;
- shortcodes, admin-post, AJAX, cron hooks и HMAC public-share contract;
- формат participant/statistics associations и legacy geometry cache.

Новые поля добавлять только с новым префиксным ключом, default value и fallback для старых данных.
