/*
	vSongBook by AppSmata Solutions
	http://github.com/vsongbook

	File: as-content/as-song.js
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

var as_element_revealed = null;

function as_toggle_element(elem)
{
	var e = elem ? document.getElementById(elem) : null;

	if (e && e.as_disabled)
		e = null;

	if (e && (as_element_revealed == e)) {
		as_conceal(as_element_revealed, 'form');
		as_element_revealed = null;

	} else {
		if (as_element_revealed)
			as_conceal(as_element_revealed, 'form');

		if (e) {
			if (e.as_load && !e.as_loaded) {
				e.as_load();
				e.as_loaded = true;
			}

			if (e.as_show)
				e.as_show();

			as_reveal(e, 'form', function() {
				var t = $(e).offset().top;
				var h = $(e).height() + 16;
				var wt = $(window).scrollTop();
				var wh = $(window).height();

				if ((t < wt) || (t > (wt + wh)))
					as_scroll_page_to(t);
				else if ((t + h) > (wt + wh))
					as_scroll_page_to(t + h - wh);

				if (e.as_focus)
					e.as_focus();
			});
		}

		as_element_revealed = e;
	}

	return !(e || !elem); // failed to find item
}

function as_submit_review(songid, elem)
{
	var params = as_form_params('a_form');

	params.a_songid = songid;

	as_ajax_post('review', params,
		function(lines) {
			if (lines[0] == '1') {
				if (lines[1] < 1) {
					var b = document.getElementById('q_doreview');
					if (b)
						b.style.display = 'none';
				}

				var t = document.getElementById('a_list_title');
				as_set_inner_html(t, 'a_list_title', lines[2]);
				as_reveal(t, 'a_list_title');

				var e = document.createElement('div');
				e.innerHTML = lines.slice(3).join("\n");

				var c = e.firstChild;
				c.style.display = 'none';

				var l = document.getElementById('a_list');
				l.insertBefore(c, l.firstChild);

				var a = document.getElementById('anew');
				a.as_disabled = true;

				as_reveal(c, 'review');
				as_conceal(a, 'form');

			} else if (lines[0] == '0') {
				document.forms['a_form'].submit();

			} else {
				as_ajax_error();
			}
		}
	);

	as_show_waiting_after(elem, false);

	return false;
}

function as_submit_comment(songid, parentid, elem)
{
	var params = as_form_params('c_form_' + parentid);

	params.c_songid = songid;
	params.c_parentid = parentid;

	as_ajax_post('comment', params,
		function(lines) {

			if (lines[0] == '1') {
				var l = document.getElementById('c' + parentid + '_list');
				l.innerHTML = lines.slice(2).join("\n");
				l.style.display = '';

				var a = document.getElementById('c' + parentid);
				a.as_disabled = true;

				var c = document.getElementById(lines[1]); // id of comment
				if (c) {
					c.style.display = 'none';
					as_reveal(c, 'comment');
				}

				as_conceal(a, 'form');

			} else if (lines[0] == '0') {
				document.forms['c_form_' + parentid].submit();

			} else {
				as_ajax_error();
			}

		}
	);

	as_show_waiting_after(elem, false);

	return false;
}

function as_review_click(reviewid, songid, target)
{
	var params = {};

	params.reviewid = reviewid;
	params.songid = songid;
	params.code = target.form.elements.code.value;
	params[target.name] = target.value;

	as_ajax_post('click_a', params,
		function(lines) {
			if (lines[0] == '1') {
				as_set_inner_html(document.getElementById('a_list_title'), 'a_list_title', lines[1]);

				var l = document.getElementById('a' + reviewid);
				var h = lines.slice(2).join("\n");

				if (h.length)
					as_set_outer_html(l, 'review', h);
				else
					as_conceal(l, 'review');

			} else {
				target.form.elements.as_click.value = target.name;
				target.form.submit();
			}
		}
	);

	as_show_waiting_after(target, false);

	return false;
}

function as_comment_click(commentid, songid, parentid, target)
{
	var params = {};

	params.commentid = commentid;
	params.songid = songid;
	params.parentid = parentid;
	params.code = target.form.elements.code.value;
	params[target.name] = target.value;

	as_ajax_post('click_c', params,
		function(lines) {
			if (lines[0] == '1') {
				var l = document.getElementById('c' + commentid);
				var h = lines.slice(1).join("\n");

				if (h.length)
					as_set_outer_html(l, 'comment', h);
				else
					as_conceal(l, 'comment');

			} else {
				target.form.elements.as_click.value = target.name;
				target.form.submit();
			}
		}
	);

	as_show_waiting_after(target, false);

	return false;
}

function as_show_comments(songid, parentid, elem)
{
	var params = {};

	params.c_songid = songid;
	params.c_parentid = parentid;

	as_ajax_post('show_cs', params,
		function(lines) {
			if (lines[0] == '1') {
				var l = document.getElementById('c' + parentid + '_list');
				l.innerHTML = lines.slice(1).join("\n");
				l.style.display = 'none';
				as_reveal(l, 'comments');

			} else {
				as_ajax_error();
			}
		}
	);

	as_show_waiting_after(elem, true);

	return false;
}

function as_form_params(formname)
{
	var es = document.forms[formname].elements;
	var params = {};

	for (var i = 0; i < es.length; i++) {
		var e = es[i];
		var t = (e.type || '').toLowerCase();

		if (((t != 'checkbox') && (t != 'radio')) || e.checked)
			params[e.name] = e.value;
	}

	return params;
}

function as_scroll_page_to(scroll)
{
	$('html,body').animate({scrollTop: scroll}, 400);
}
