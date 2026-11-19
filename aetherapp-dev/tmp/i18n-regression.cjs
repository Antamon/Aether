const {spawnSync}=require('node:child_process');
const suites=[
 'tests/character_action_navigation_test.cjs','tests/character_delete_controls_test.cjs','tests/company_details_card_test.cjs','tests/company_research_ui_test.cjs',
 'tests/company_selection_test.cjs','tests/company_snapshot_money_test.cjs','tests/diary_cards_test.cjs','tests/economy_tables_test.cjs',
 'tests/event_editor_test.cjs','tests/event_navigation_test.cjs','tests/portfolio_refresh_test.cjs','tests/request-sharing.test.cjs','tests/share_purchase_ui_test.cjs',
 'tests/sidebar_access_test.cjs','tests/trait_actions_browser_test.cjs','tests/modal_frame_test.cjs','tests/research_conclusion_qr_test.cjs','tests/research_feedback_edit_test.cjs'
];
const from=Number(process.argv[2]||0),to=Number(process.argv[3]||suites.length);let failed=0;
for(const test of suites.slice(from,to)){
 const result=spawnSync(process.execPath,[test],{encoding:'utf8',timeout:45000});
 console.log((result.status===0?'PASS ':'FAIL ')+test+'\n'+(result.stdout||'').trim()+'\n'+(result.stderr||'').trim());if(result.status!==0)failed++;
}
process.exitCode=failed?1:0;
