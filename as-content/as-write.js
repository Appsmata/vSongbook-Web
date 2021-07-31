/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-content/as-post.js
	Description: THIS FILE HAS BEEN DEPRECATED IN FAVOUR OF as-global.js


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this online
*/

function as_title_change(value)
{
	as_ajax_post('posttitle', {title: value}, function(lines) {
		if (lines[0] == '1') {
			if (lines[1].length) {
				as_tags_examples = lines[1];
				as_tag_hints(true);
			}

			if (lines.length > 2) {
				var simelem = document.getElementById('similar');
				if (simelem)
					simelem.innerHTML = lines.slice(2).join('\n');
			}

		} else if (lines[0] == '0')
			alert(lines[1]);
		else
			as_ajax_error();
	});

	as_show_waiting_after(document.getElementById('similar'), true);
}

function as_html_unescape(html)
{
	return html.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
}

function as_html_escape(text)
{
	return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function as_tag_click(link)
{
	var elem = document.getElementById('tags');
	var parts = as_tag_typed_parts(elem);

	// removes any HTML tags and ampersand
	var tag = as_html_unescape(link.innerHTML.replace(/<[^>]*>/g, ''));

	var separator = as_tag_onlycomma ? ', ' : ' ';

	// replace if matches typed, otherwise append
	var newvalue = (parts.typed && (tag.toLowerCase().indexOf(parts.typed.toLowerCase()) >= 0))
		? (parts.before + separator + tag + separator + parts.after + separator) : (elem.value + separator + tag + separator);

	// sanitize and set value
	if (as_tag_onlycomma)
		elem.value = newvalue.replace(/[\s,]*,[\s,]*/g, ', ').replace(/^[\s,]+/g, '');
	else
		elem.value = newvalue.replace(/[\s,]+/g, ' ').replace(/^[\s,]+/g, '');

	elem.focus();
	as_tag_hints();

	return false;
}

function as_tag_hints(skipcomplete)
{
	var elem = document.getElementById('tags');
	var html = '';
	var completed = false;

	// first try to auto-complete
	if (as_tags_complete && !skipcomplete) {
		var parts = as_tag_typed_parts(elem);

		if (parts.typed) {
			html = as_tags_to_html((as_html_unescape(as_tags_examples + ',' + as_tags_complete)).split(','), parts.typed.toLowerCase());
			completed = html ? true : false;
		}
	}

	// otherwise show examples
	if (as_tags_examples && !completed)
		html = as_tags_to_html((as_html_unescape(as_tags_examples)).split(','), null);

	// set title visiblity and hint list
	document.getElementById('tag_examples_title').style.display = (html && !completed) ? '' : 'none';
	document.getElementById('tag_complete_title').style.display = (html && completed) ? '' : 'none';
	document.getElementById('tag_hints').innerHTML = html;
}

function as_tags_to_html(tags, matchlc)
{
	var html = '';
	var added = 0;
	var tagseen = {};

	for (var i = 0; i < tags.length; i++) {
		var tag = tags[i];
		var taglc = tag.toLowerCase();

		if (!tagseen[taglc]) {
			tagseen[taglc] = true;

			if ((!matchlc) || (taglc.indexOf(matchlc) >= 0)) { // match if necessary
				if (matchlc) { // if matching, show appropriate part in bold
					var matchstart = taglc.indexOf(matchlc);
					var matchend = matchstart + matchlc.length;
					inner = '<span style="font-weight:normal;">' + as_html_escape(tag.substring(0, matchstart)) + '<b>' +
						as_html_escape(tag.substring(matchstart, matchend)) + '</b>' + as_html_escape(tag.substring(matchend)) + '</span>';
				} else // otherwise show as-is
					inner = as_html_escape(tag);

				html += as_tag_template.replace(/\^/g, inner.replace('$', '$$$$')) + ' '; // replace ^ in template, escape $s

				if (++added >= as_tags_max)
					break;
			}
		}
	}

	return html;
}

function as_caret_from_end(elem)
{
	if (document.selection) { // for IE
		elem.focus();
		var sel = document.selection.createRange();
		sel.moveStart('character', -elem.value.length);

		return elem.value.length - sel.text.length;

	} else if (typeof (elem.selectionEnd) != 'undefined') // other browsers
		return elem.value.length - elem.selectionEnd;

	else // by default return safest value
		return 0;
}

function as_tag_typed_parts(elem)
{
	var caret = elem.value.length - as_caret_from_end(elem);
	var active = elem.value.substring(0, caret);
	var passive = elem.value.substring(active.length);

	// if the caret is in the middle of a word, move the end of word from passive to active
	if (
		active.match(as_tag_onlycomma ? /[^\s,][^,]*$/ : /[^\s,]$/) &&
		(adjoinmatch = passive.match(as_tag_onlycomma ? /^[^,]*[^\s,][^,]*/ : /^[^\s,]+/))
		) {
		active += adjoinmatch[0];
		passive = elem.value.substring(active.length);
	}

	// find what has been typed so far
	var typedmatch = active.match(as_tag_onlycomma ? /[^\s,]+[^,]*$/ : /[^\s,]+$/) || [''];

	return {before: active.substring(0, active.length - typedmatch[0].length), after: passive, typed: typedmatch[0]};
}

function as_category_select(idprefix, startpath)
{
	var startval = startpath ? startpath.split("/") : [];
	var setdescnow = true;

	for (var l = 0; l <= as_cat_maxdepth; l++) {
		var elem = document.getElementById(idprefix + '_' + l);

		if (elem) {
			if (l) {
				if (l < startval.length && startval[l].length) {
					var val = startval[l];

					for (var j = 0; j < elem.options.length; j++)
						if (elem.options[j].value == val)
							elem.selectedIndex = j;
				} else
					var val = elem.options[elem.selectedIndex].value;
			} else
				val = '';

			if (elem.as_last_sel !== val) {
				elem.as_last_sel = val;

				var subelem = document.getElementById(idprefix + '_' + l + '_sub');
				if (subelem)
					subelem.parentNode.removeChild(subelem);

				if (val.length || (l == 0)) {
					subelem = elem.parentNode.insertBefore(document.createElement('span'), elem.nextSibling);
					subelem.id = idprefix + '_' + l + '_sub';
					as_show_waiting_after(subelem, true);

					as_ajax_post('category', {categoryid: val},
						(function(elem, l) {
							return function(lines) {
								var subelem = document.getElementById(idprefix + '_' + l + '_sub');
								if (subelem)
									subelem.parentNode.removeChild(subelem);

								if (lines[0] == '1') {
									elem.as_cat_desc = lines[1];

									var addedoption = false;

									if (lines.length > 2) {
										subelem = elem.parentNode.insertBefore(document.createElement('span'), elem.nextSibling);
										subelem.id = idprefix + '_' + l + '_sub';
										subelem.innerHTML = ' ';

										var newelem = elem.cloneNode(false);

										newelem.name = newelem.id = idprefix + '_' + (l + 1);
										newelem.options.length = 0;

										if (l ? as_cat_allownosub : as_cat_allownone)
											newelem.options[0] = new Option(l ? '' : elem.options[0].text, '', true, true);

										for (var i = 2; i < lines.length; i++) {
											var parts = lines[i].split('/');

											if (String(as_cat_exclude).length && (String(as_cat_exclude) == parts[0]))
												continue;

											newelem.options[newelem.options.length] = new Option(parts.slice(1).join('/'), parts[0]);
											addedoption = true;
										}

										if (addedoption) {
											subelem.appendChild(newelem);
											as_category_select(idprefix, startpath);

										}

										if (l == 0)
											elem.style.display = 'none';
									}

									if (!addedoption)
										set_category_description(idprefix);

								} else if (lines[0] == '0')
									alert(lines[1]);
								else
									as_ajax_error();
							}
						})(elem, l)
					);

					setdescnow = false;
				}

				break;
			}
		}
	}

	if (setdescnow)
		set_category_description(idprefix);
}

function set_category_description(idprefix)
{
	var n = document.getElementById(idprefix + '_note');

	if (n) {
		desc = '';

		for (var l = 1; l <= as_cat_maxdepth; l++) {
			var elem = document.getElementById(idprefix + '_' + l);

			if (elem && elem.options[elem.selectedIndex].value.length)
				desc = elem.as_cat_desc;
		}

		n.innerHTML = desc;
	}
}
