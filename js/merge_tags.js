if (window.gform)
	gform.addFilter('gform_merge_tags', 'gf_helpscout_merge_tags');

function gf_helpscout_merge_tags(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {
	var tags = [{
		tag: '{helpscout:id}',
		label: gform_helpscout_merge_tags_strings.id
	}, {
		tag: '{helpscout:number}',
		label: gform_helpscout_merge_tags_strings.number
	}, {
		tag: '{helpscout:status}',
		label: gform_helpscout_merge_tags_strings.status
	}, {
		tag: '{helpscout:subject}',
		label: gform_helpscout_merge_tags_strings.subject
	}, {
		tag: '{helpscout:url}',
		label: gform_helpscout_merge_tags_strings.url
	}];

	mergeTags['gf_helpscout'] = {
		label: 'Help Scout',
		tags: tags
	};

	return mergeTags;
}
