import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const outputPath = process.argv[2] ? path.resolve(process.argv[2]) : path.join(repoRoot, ".build", "demo-backup", "manifest.json");
const releaseManifest = JSON.parse(fs.readFileSync(path.join(repoRoot, "update-manifest.json"), "utf8"));
const version = String(releaseManifest.version || "0.0.0");
const currentYear = 2026;
const previousYear = 2025;

const md5 = (value) => crypto.createHash("md5").update(value, "utf8").digest("hex");
const stationStreet = "Bredowstraße 4";
const stationPostcode = "22113";
const stationCity = "Hamburg";
const stationCenter = [53.52832, 10.08321];

const categoryDefinitions = [
  [101, "FEU", "Feuer"],
  [102, "FEUK", "Kleinalarm (z.B. Müllcontainer oder Pkw)"],
  [103, "FEUBMA", "Feuermeldung durch Brandmeldeanlage"],
  [104, "TH", "Hilfeleistungseinsatz"],
  [105, "THY", "Menschenleben in Gefahr"],
  [106, "TV", "Tür verschlossen"],
  [107, "WASSER", "Wasserschaden"],
  [108, "TIER", "Tier in Notlage"],
  [109, "DRZF", "Droht zu fallen"],
  [110, "ALARM", "Alarmmeldung"],
];

const calls = [
  [currentYear, "2026-01-14", "06:42", "FEUBMA", "Bredowstraße", "4", "22113", 53.52832, 10.08321, "Ausgelöste Brandmeldeanlage"],
  [currentYear, "2026-02-03", "18:17", "TH", "Mönckebergstraße", "7", "20095", 53.55118, 10.00111, "Technische Hilfeleistung"],
  [currentYear, "2026-03-22", "13:05", "FEUK", "Reeperbahn", "91", "20359", 53.54962, 9.96314, "Kleinbrand im Außenbereich"],
  [currentYear, "2026-04-09", "21:36", "TV", "Eppendorfer Baum", "22", "20249", 53.59012, 9.98515, "Türöffnung mit Notfallverdacht"],
  [currentYear, "2026-05-18", "09:24", "WASSER", "Wandsbeker Marktstraße", "48", "22041", 53.57184, 10.06716, "Wasserschaden in einem Gebäude"],
  [currentYear, "2026-06-27", "16:51", "THY", "Elbchaussee", "215", "22605", 53.55231, 9.87562, "Verkehrsunfall mit verletzter Person"],
  [currentYear, "2026-07-11", "11:08", "TIER", "Alsterdorfer Straße", "265", "22297", 53.61052, 10.01294, "Tier in einer Notlage"],
  [currentYear, "2026-08-02", "23:19", "FEU", "Billhorner Röhrendamm", "94", "20539", 53.54192, 10.03928, "Gemeldete Rauchentwicklung"],
  [currentYear, "2026-08-29", "15:43", "DRZF", "Bergedorfer Straße", "112", "21029", 53.48747, 10.21491, "Ast drohte auf Fahrbahn zu fallen"],
  [currentYear, "2026-09-12", "04:26", "ALARM", "Steindamm", "58", "20099", 53.55411, 10.01772, "Erkundung nach Alarmmeldung"],
  [previousYear, "2025-01-08", "07:15", "TH", "Langenhorner Chaussee", "321", "22419", 53.66552, 10.01181, "Sturmschaden beseitigt"],
  [previousYear, "2025-01-26", "19:48", "FEUK", "Fuhlsbüttler Straße", "401", "22309", 53.60957, 10.04281, "Brennender Papierkorb"],
  [previousYear, "2025-02-17", "10:32", "FEUBMA", "Überseering", "17", "22297", 53.60321, 10.02221, "Brandmeldeanlage ausgelöst"],
  [previousYear, "2025-03-06", "14:11", "WASSER", "Harburger Ring", "10", "21073", 53.45973, 9.98165, "Wasser im Keller"],
  [previousYear, "2025-03-29", "22:54", "TV", "Schanzenstraße", "44", "20357", 53.56277, 9.96308, "Hilflose Person vermutet"],
  [previousYear, "2025-04-15", "05:39", "THY", "Kieler Straße", "303", "22525", 53.58566, 9.93404, "Verkehrsunfall"],
  [previousYear, "2025-05-02", "17:20", "FEU", "Rothenbaumchaussee", "79", "20148", 53.57362, 9.98982, "Rauchentwicklung aus Wohnung"],
  [previousYear, "2025-05-24", "12:07", "TIER", "Poppenbütteler Weg", "132", "22399", 53.65686, 10.07443, "Tier aus Schacht gerettet"],
  [previousYear, "2025-06-13", "08:46", "ALARM", "Ausschläger Elbdeich", "2", "20539", 53.53562, 10.04401, "Unklare Rauchentwicklung"],
  [previousYear, "2025-07-05", "20:33", "FEUK", "Luruper Hauptstraße", "247", "22547", 53.59124, 9.87311, "Kleinbrand an einem Container"],
  [previousYear, "2025-07-28", "03:58", "FEUBMA", "Große Bleichen", "21", "20354", 53.55284, 9.98978, "Automatische Brandmeldung"],
  [previousYear, "2025-08-19", "16:14", "DRZF", "Osdorfer Landstraße", "122", "22549", 53.57214, 9.86073, "Baumteil drohte zu fallen"],
  [previousYear, "2025-09-07", "09:41", "TH", "Curslacker Neuer Deich", "50", "21029", 53.48118, 10.21163, "Betriebsmittel aufgenommen"],
  [previousYear, "2025-10-16", "18:29", "WASSER", "Neugrabener Bahnhofstraße", "18", "21149", 53.47413, 9.85364, "Wasserleitung beschädigt"],
  [previousYear, "2025-12-21", "23:12", "FEU", "Jenfelder Allee", "80", "22045", 53.57537, 10.13383, "Feuerschein im Freien"],
];

const participantNames = [
  ["Anna", "Bergmann"], ["Lukas", "Brandt"], ["Miriam", "Clausen"], ["Jonas", "Dierks"], ["Leonie", "Eggers"],
  ["Felix", "Fischer"], ["Sophie", "Grimm"], ["Tobias", "Hansen"], ["Nina", "Jensen"], ["David", "Krüger"],
  ["Laura", "Lange"], ["Mehmet", "Mertens"], ["Paula", "Neumann"], ["Daniel", "Peters"], ["Emma", "Reimers"],
  ["Jan", "Schulz"], ["Sarah", "Thiel"], ["Marco", "Vogt"], ["Clara", "Wagner"], ["Tim", "Zimmermann"],
  ["Aylin", "Özdemir"], ["Ben", "Martens"], ["Maja", "Albrecht"], ["Ole", "Kramer"], ["Linda", "Seifert"],
];

const functions = ["Maschinist", "Gruppenführer", "ATF", "ATM", "Melder", "WTF", "WTM", "STF", "STM", "Mannschaft"];
const categoryIdByCode = Object.fromEntries(categoryDefinitions.map(([id, code]) => [code, id]));

function meta(key, value) {
  return { key, value };
}

const participants = participantNames.map(([vorname, nachname], index) => ({
  id: index + 1,
  vorname,
  nachname,
  job_title: "Einsatzkraft",
  entry_date: `${2010 + (index % 14)}-0${(index % 9) + 1}-01`,
  rank_title: index % 7 === 0 ? "Hauptbrandmeister/in" : index % 4 === 0 ? "Oberbrandmeister/in" : "Feuerwehrmann/-frau",
  member_function: index % 8 === 0 ? "Führungskraft" : "Einsatzabteilung",
  education: "Grundausbildung und regelmäßige Fortbildung",
  description: "Demodatensatz für die Präsentation der Teilnehmerverwaltung.",
  sort_order: index + 1,
  category_ids: "[]",
  gallery_ids: "[]",
  primary_image_id: 0,
  default_functions: JSON.stringify([functions[index % functions.length]]),
  is_archived: 0,
  is_deleted: 0,
  created_at: `${previousYear}-01-01 09:00:00`,
}));

let statId = 1;
const stats = [];
const reports = calls.map((call, index) => {
  const [year, date, time, code, street, houseNumber, postcode, lat, lng, title] = call;
  const postId = 1001 + index;
  const assignments = Array.from({ length: 8 }, (_, offset) => {
    const participantId = ((index * 3 + offset) % participants.length) + 1;
    const participantFunction = functions[offset % functions.length];
    stats.push({
      id: statId++,
      post_id: postId,
      teilnehmer_id: participantId,
      funktion: participantFunction,
      created_at: `${date} ${time}:00`,
    });
    return { id: participantId, funktion: participantFunction };
  });
  const fullTitle = `${code} – ${title}`;

  return {
    id: postId,
    post: {
      post_title: fullTitle,
      post_content: `<p>Demoeinsatz in Hamburg: ${title}. Die dargestellten Angaben sind vollständig erfunden und dienen ausschließlich zur Vorführung des Plugins.</p><p>Nach Abschluss der Maßnahmen wurde die Einsatzstelle an die zuständige Stelle übergeben.</p>`,
      post_excerpt: `Demoeinsatz ${code} in Hamburg.`,
      post_status: "publish",
      post_date: `${date} ${time}:00`,
      post_name: `demo-${year}-${String(index + 1).padStart(2, "0")}-${code.toLowerCase()}`,
      comment_status: "closed",
      ping_status: "closed",
      menu_order: 0,
      author_login: "",
    },
    categories: [100, categoryIdByCode[code]],
    meta: [
      meta("_feu_einsatz_einsatzbericht", "1"),
      meta("_feu_einsatz_demo_record", "1"),
      meta("_feu_einsatz_strasse", street),
      meta("_feu_einsatz_hausnummer", houseNumber),
      meta("_feu_einsatz_plz", postcode),
      meta("_feu_einsatz_stadt", "Hamburg"),
      meta("_feu_einsatz_datum", date),
      meta("_feu_einsatz_uhrzeit", time),
      meta("_feu_einsatz_latitude", String(lat)),
      meta("_feu_einsatz_longitude", String(lng)),
      meta("_feu_einsatz_teilnehmer", assignments),
      meta("_feu_einsatz_organisationen", [1, 2, index % 3 === 0 ? 4 : 3]),
      meta("_feu_einsatz_comments_enabled", "0"),
      meta("_feu_einsatz_availability_mode", "always"),
    ],
  };
});

const stationCoordinatesKey = md5(`${stationStreet}|${stationPostcode}|${stationCity}`);

const manifest = {
  manifest_version: 1,
  created_at: "2026-09-19 12:00:00",
  archive_key: `demo-hamburg-${version}`,
  label: `Hamburg Demo ${version}`,
  creator: { id: 0, login: "", display_name: "Demo Generator", email: "" },
  site: {
    name: "Feuerwehrakademie Hamburg – Demo",
    home_url: "https://demo.invalid/",
    site_url: "https://demo.invalid/",
    upload_basedir: "",
    upload_baseurl: "",
  },
  plugin: { version, schema_version: "2026-07-03-1" },
  options: {
    feu_einsatz_version: version,
    feu_einsatz_schema_version: "2026-07-03-1",
    feu_einsatz_setup_wizard_pending: 0,
    feu_einsatz_setup_completed_at: "2026-09-19 12:00:00",
    feu_einsatz_default_categories_prompt: 0,
    feu_einsatz_categories: categoryDefinitions.map(([id]) => id),
    feu_einsatz_functions: functions,
    feu_einsatz_default_participant_function: "Mannschaft",
    feu_einsatz_area_station_street: stationStreet,
    feu_einsatz_area_station_postcode: stationPostcode,
    feu_einsatz_area_station_city: stationCity,
    feu_einsatz_area_station_logo_id: 0,
    feu_einsatz_area_station_logo_size: 48,
    feu_einsatz_photo_watermark_text: "Feuerwehrakademie Hamburg",
    feu_einsatz_map_zoom: 16,
    feu_einsatz_map_height: 460,
    feu_einsatz_auto_map_image: 1,
    feu_einsatz_map_preview_highlight_color: "#e11d48",
    feu_einsatz_map_label_text_color: "#ffffff",
    feu_einsatz_map_preview_stroke_width: 9,
    feu_einsatz_map_label_style: "bubble",
    feu_einsatz_single_map_display_mode: "live",
    feu_einsatz_single_map_privacy_mode: "always",
    feu_einsatz_single_live_map_show_station: 1,
    feu_einsatz_overview_show_stats: 1,
    feu_einsatz_overview_show_year_filter: 1,
    feu_einsatz_area_page_enabled: 1,
    feu_einsatz_area_show_calls: 1,
    feu_einsatz_area_postcodes: ["20095", "20099", "20354", "20357", "20359", "20539", "21029", "21073", "21149", "22041", "22045", "22113", "22297", "22309", "22399", "22419", "22525", "22547", "22549", "22605"],
    feu_einsatz_feature_organizations_enabled: 1,
    feu_einsatz_default_comments_enabled: 0,
    feu_einsatz_default_card_variant: "modern",
    feu_einsatz_related_reports_display: "cards",
    feu_einsatz_related_reports_count: 6,
  },
  transients: {
    [`_transient_feu_einsatz_area_station_${stationCoordinatesKey}`]: { latitude: stationCenter[0], longitude: stationCenter[1] },
    [`_transient_timeout_feu_einsatz_area_station_${stationCoordinatesKey}`]: Math.floor(Date.now() / 1000) + 604800,
  },
  reports,
  comments: [],
  terms: [
    { term_id: 100, name: "Einsätze", slug: "einsaetze", description: "Demokategorie für Einsatzberichte", parent: 0 },
    ...categoryDefinitions.map(([termId, name, description]) => ({
      term_id: termId,
      name,
      slug: name.toLowerCase().replace(/[^a-z0-9]+/g, "-"),
      description,
      parent: 100,
    })),
  ],
  attachments: [],
  tables: {
    participants,
    stats,
    statistics_cache: [],
    organizations: [
      { id: 1, name: "Berufsfeuerwehr", color: "#b91c1c", post_link: "", is_archived: 0, sort_order: 1, created_at: "2025-01-01 09:00:00" },
      { id: 2, name: "Freiwillige Feuerwehr", color: "#dc2626", post_link: "", is_archived: 0, sort_order: 2, created_at: "2025-01-01 09:00:00" },
      { id: 3, name: "Rettungsdienst", color: "#0f766e", post_link: "", is_archived: 0, sort_order: 3, created_at: "2025-01-01 09:00:00" },
      { id: 4, name: "Polizei", color: "#1d4ed8", post_link: "", is_archived: 0, sort_order: 4, created_at: "2025-01-01 09:00:00" },
      { id: 5, name: "THW", color: "#d97706", post_link: "", is_archived: 0, sort_order: 5, created_at: "2025-01-01 09:00:00" },
      { id: 6, name: "Drehleiter", color: "#ea580c", post_link: "", is_archived: 0, sort_order: 6, created_at: "2025-01-01 09:00:00" },
    ],
    logs: [{
      id: 1,
      user_id: 0,
      user_name: "Demo Generator",
      action_type: "demo_backup_created",
      entity_type: "archive",
      entity_id: 0,
      message: "Hamburg-Demodaten wurden erstellt.",
      details: JSON.stringify({ reports: reports.length, participants: participants.length, current_year: currentYear, previous_year: previousYear }),
      page_slug: "feu-einsatz-archive",
      page_url: "",
      ip_address: "",
      created_at: "2026-09-19 12:00:00",
    }],
  },
  summary: {
    archive_key: `demo-hamburg-${version}`,
    label: `Hamburg Demo ${version}`,
    created_at: "2026-09-19 12:00:00",
    reports: reports.length,
    comments: 0,
    attachments: 0,
    participants: participants.length,
    statistics_cache: 0,
    organizations: 6,
    logs: 1,
  },
};

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, `${JSON.stringify(manifest, null, 2)}\n`, "utf8");
process.stdout.write(`demo-manifest:${outputPath}:${reports.length}:${participants.length}\n`);
