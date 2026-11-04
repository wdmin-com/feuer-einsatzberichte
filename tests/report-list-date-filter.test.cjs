const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('report list sorts and filters by event date instead of modification date', () => {
    const admin = read('includes/class-admin.php');
    const template = read('templates/admin/reports.php');
    const styles = read('assets/admin/css/report-list.css');
    const script = read('assets/admin/js/report-map-review.js');

    assert.match(admin, /filter_report_list_query_clauses/);
    assert.match(admin, /STR_TO_DATE\(\{\$event_date_alias\}\.meta_value, '%Y-%m-%d'\)/);
    assert.match(admin, /feu_einsatz_date_from/);
    assert.match(admin, /feu_einsatz_date_to/);
    assert.match(admin, /feu_einsatz_date_order/);
    assert.match(admin, /'feu_einsatz_report_list' => '1'/);
    assert.match(template, /name="report_year"/);
    assert.match(template, /name="report_date_from"/);
    assert.match(template, /name="report_date_to"/);
    assert.match(template, /Neueste Einsätze zuerst/);
    assert.match(template, /FEU_Einsatz_Template_Helpers::get_event_datetime/);
    assert.match(template, /feu-report-list-filter-date/);
    assert.match(styles, /grid-template-columns:repeat\(6,minmax\(0,1fr\)\)/);
    assert.match(styles, /height:44px;min-height:44px/);
    assert.doesNotMatch(styles, /feu-report-list-period/);
    assert.match(script, /yearFilter\.value = '0'/);
    assert.match(script, /field\.value = ''/);
});
