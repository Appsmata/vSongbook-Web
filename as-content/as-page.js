/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-content/as-page.js
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

function as_reveal(elem, type, callback)
{
	if (elem)
		$(elem).slideDown(400, callback);
}

function as_conceal(elem, type, callback)
{
	if (elem)
		$(elem).slideUp(400);
}

function as_set_inner_html(elem, type, html)
{
	if (elem)
		elem.innerHTML = html;
}

function as_set_outer_html(elem, type, html)
{
	if (elem) {
		var e = document.createElement('div');
		e.innerHTML = html;
		elem.parentNode.replaceChild(e.firstChild, elem);
	}
}

function as_show_waiting_after(elem, inside)
{
	if (elem && !elem.as_waiting_shown) {
		var w = document.getElementById('as-waiting-template');

		if (w) {
			var c = w.cloneNode(true);
			c.id = null;

			if (inside)
				elem.insertBefore(c, null);
			else
				elem.parentNode.insertBefore(c, elem.nextSibling);

			elem.as_waiting_shown = c;
		}
	}
}

function as_hide_waiting(elem)
{
	var c = elem.as_waiting_shown;

	if (c) {
		c.parentNode.removeChild(c);
		elem.as_waiting_shown = null;
	}
}

function as_thumb_click(elem)
{
	var ens = elem.name.split('_');
	var postid = ens[1];
	var thumb = parseInt(ens[2]);
	var code = elem.form.elements.code.value;
	var anchor = ens[3];

	as_ajax_post('thumb', {postid: postid, thumb: thumb, code: code},
		function(lines) {
			if (lines[0] == '1') {
				as_set_inner_html(document.getElementById('thumbing_' + postid), 'thumbing', lines.slice(1).join("\n"));

			} else if (lines[0] == '0') {
				var mess = document.getElementById('errorbox');

				if (!mess) {
					mess = document.createElement('div');
					mess.id = 'errorbox';
					mess.className = 'as-error';
					mess.innerHTML = lines[1];
					mess.style.display = 'none';
				}

				var postelem = document.getElementById(anchor);
				var e = postelem.parentNode.insertBefore(mess, postelem);
				as_reveal(e);

			} else
				as_ajax_error();
		}
	);

	return false;
}

function as_notice_click(elem)
{
	var ens = elem.name.split('_');
	var code = elem.form.elements.code.value;

	as_ajax_post('notice', {noticeid: ens[1], code: code},
		function(lines) {
			if (lines[0] == '1')
				as_conceal(document.getElementById('notice_' + ens[1]), 'notice');
			else if (lines[0] == '0')
				alert(lines[1]);
			else
				as_ajax_error();
		}
	);

	return false;
}

function as_favorite_click(elem)
{
	var ens = elem.name.split('_');
	var code = elem.form.elements.code.value;

	as_ajax_post('favorite', {entitytype: ens[1], entityid: ens[2], favorite: parseInt(ens[3]), code: code},
		function(lines) {
			if (lines[0] == '1')
				as_set_inner_html(document.getElementById('favoriting'), 'favoriting', lines.slice(1).join("\n"));
			else if (lines[0] == '0') {
				alert(lines[1]);
				as_hide_waiting(elem);
			} else
				as_ajax_error();
		}
	);

	as_show_waiting_after(elem, false);

	return false;
}

function as_ajax_post(operation, params, callback)
{
	$.extend(params, {as: 'ajax', as_operation: operation, as_root: as_root, as_request: as_request});

	$.post(as_root, params, function(response) {
		var header = 'AS_AJAX_RESPONSE';
		var headerpos = response.indexOf(header);

		if (headerpos >= 0)
			callback(response.substr(headerpos + header.length).replace(/^\s+/, '').split("\n"));
		else
			callback([]);

	}, 'text').fail(function(jqXHR) {
		if (jqXHR.readyState > 0)
			callback([])
	});
}

function as_ajax_error()
{
	alert('Unexpected response from server - please try again or switch off Javascript.');
}

function as_display_rule_show(target, show, first)
{
	var e = document.getElementById(target);
	if (e) {
		if (first || e.nodeName == 'SPAN')
			e.style.display = (show ? '' : 'none');
		else if (show)
			$(e).fadeIn();
		else
			$(e).fadeOut();
	}
}
