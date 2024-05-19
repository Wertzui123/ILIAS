/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ******************************************************************** */

il.COPagePres = {
  /**
	 * Basic init function
	 */
  init() {
    this.initToc();
    this.initInteractiveImages();
    this.updateQuestionOverviews();
    this.initMapAreas();
    this.initAdvancedContent();
    this.initAudioVideo();
    this.initAccordions();
  },

  //
  // Toc (as used in Wikis)
  //

  /**
	 * Init the table of content
	 */
  initToc() {
    // init toc
    const cookiePos = document.cookie.indexOf('pg_hidetoc=');
    if (cookiePos > -1 && document.cookie.charAt(cookiePos + 11) == 1) {
      this.toggleToc();
    }
  },

  initAccordions() {
    if (typeof ilAccordionsInits !== 'undefined') {
      for (let i = 0; i < ilAccordionsInits.length; i++) {
        il.Accordion.add(ilAccordionsInits[i]);
      }
    }
  },

  /**
	 * Toggle the table of content
	 */
  toggleToc() {
    let toc_on; let toc_off; const
      toc = document.getElementById('ilPageTocContent');

    if (!toc) {
      return;
    }

    toc_on = document.getElementById('ilPageTocOn');
    toc_off = document.getElementById('ilPageTocOff');

    if (toc && toc.style.display == 'none') {
      toc.style.display = 'block';
      toc_on.style.display = 'none';
      toc_off.style.display = '';
      document.cookie = 'pg_hidetoc=0';
    } else {
      toc_on.style.display = '';
      toc_off.style.display = 'none';
      toc.style.display = 'none';
      document.cookie = 'pg_hidetoc=1';
    }
  },

  //
  // Interactive Images
  //

  iim_trigger: {},
  iim_area: {},
  iim_popup: {},
  iim_marker: {},
  dragging: false,

  /**
	 * Init interactive images
	 */
  initInteractiveImages() {
    // preload overlay images (necessary?)
    // add onmouseover event to all map areas
    // $("map.iim > area").mouseover(this.overBaseArea);
    // $("map.iim > area").mouseout(this.outBaseArea);
    // $("map.iim > area").click(this.clickBaseArea);

    // $("a.ilc_marker_Marker").mouseover(this.overMarker);
    // $("a.ilc_marker_Marker").mouseout(this.outMarker);
    // $("a.ilc_marker_Marker").click(this.clickMarker);

    // add areas
    document.querySelectorAll("[data-copg-iim-data-type='area']").forEach((el) => {
      const d = el.dataset;
      il.COPagePres.addIIMArea({
        area_id: d.copgIimAreaId,
        iim_id: d.copgIimId,
        tr_nr: d.copgIimTrNr,
        title: d.copgIimTitle,
      });
    });

    // add trigger for overlays/popups
    document.querySelectorAll("[data-copg-iim-data-type='trigger']").forEach((el) => {
      const d = el.dataset;
      il.COPagePres.addIIMTrigger({
        iim_id: d.copgIimId,
        type: d.copgIimType,
        title: d.copgIimTitle,
        ovx: d.copgIimOvx,
        ovy: d.copgIimOvy,
        markx: d.copgIimMarkx,
        marky: d.copgIimMarky,
        popup_nr: d.copgIimPopupNr,
        nr: d.copgIimNr,
        popx: d.copgIimPopx,
        popy: d.copgIimPopy,
        popwidth: d.copgIimPopwidth,
        popheight: d.copgIimPopheight,
        tr_id: d.copgIimTrId,
      });
    });

    // add markers
    document.querySelectorAll("[data-copg-iim-data-type='marker']").forEach((el) => {
      const d = el.dataset;
      il.COPagePres.addIIMMarker({
        iim_id: d.copgIimId,
        m_id: d.copgIimMId,
        markx: d.copgIimMarkx,
        marky: d.copgIimMarky,
        tr_nr: d.copgIimTrNr,
        tr_id: d.copgIimTrId,
        edit_mode: d.copgIimEditMode,
      });
    });

    // add popups
    document.querySelectorAll("[data-copg-iim-data-type='popup']").forEach((el) => {
      const d = el.dataset;
      il.COPagePres.addIIMPopup({
        iim_id: d.copgIimId,
        pop_id: d.copgIimPopId,
        div_id: d.copgIimDivId,
        nr: d.copgIimNr,
        title: d.copgIimTitle,
      });
    });

    $(document).on('il.accordion.start-opening', (ev, el) => {
      il.COPagePres.fixMarkerPositions();
    });
  },

  /**
	 * Mouse over marker -> show the overlay image
	 */
  overMarker(e) {
    let marker_tr_nr; let
      iim_id;

    if (this.dragging) {
      return;
    }

    marker_tr_nr = il.COPagePres.iim_marker[e.target.id].tr_nr;
    iim_id = il.COPagePres.iim_marker[e.target.id].iim_id;
    il.COPagePres.handleOverEvent(iim_id, marker_tr_nr, true);
  },

  /**
	 * Mouse leaves marker -> hide the overlay image
	 */
  outMarker(e) {
    let marker_tr_nr; let
      iim_id;
    if (this.dragging) {
      return;
    }

    marker_tr_nr = il.COPagePres.iim_marker[e.target.id].tr_nr;
    iim_id = il.COPagePres.iim_marker[e.target.id].iim_id;
    il.COPagePres.handleOutEvent(iim_id, marker_tr_nr);
  },

  /**
	 * Mouse over base image map area -> show the overlay image
	 * and (on first time) init the image map of the overlay image
	 */
  overBaseArea(e) {
    const area_tr_nr = il.COPagePres.iim_area[e.target.id].tr_nr;
    const { iim_id } = il.COPagePres.iim_area[e.target.id];

    il.COPagePres.handleOverEvent(iim_id, area_tr_nr, false);
  },

  /**
	 * Mouse over base image map area or marker -> show the overlay image
	 * and (on first time) init the image map of the overlay image
	 */
  handleOverEvent(iim_id, area_tr_nr, is_marker) {
    let k; let j; let tr; let coords; let ovx; let ovy; let base; let ov; let base_map_name; let c; let k2; let i2; let
      tr2;

    if (this.dragging) {
      return;
    }

    for (k in il.COPagePres.iim_trigger) {
      tr = il.COPagePres.iim_trigger[k];

      if (tr.nr == area_tr_nr && tr.iim_id == iim_id) {
        base = $(`img#base_img_${tr.iim_id}`);
        ov = $(`img#iim_ov_${tr.tr_id}`);
        // no overlay image? -> skip
        if (ov.length == 0) {
          continue;
        }

        // no usamap (e.g. edit mode) -> skip)
        if (typeof (base.attr('usemap')) === 'undefined') {
          continue;
        }

        base_map_name = base.attr('usemap').substr(1);

        // display the overlay at the correct position
        ov.css('position', 'absolute');
        ovx = parseInt(tr.ovx, 10);
        ovy = parseInt(tr.ovy, 10);
        ov.css('display', '');

        // this fixes the position in case of the toc2win
        // view, if the fixed div has been scrolled
        $(ov).position({
          my: 'left top',
          at: `left+${ovx} top+${ovy}`,
          of: `img#base_img_${tr.iim_id}`,
          collision: 'none',
        });

        // on first time we need to initialize the
        // image map of the overlay image
        if (tr.map_initialized == null && !is_marker) {
          tr.map_initialized = true;
          $(`map[name='${base_map_name}'] > area`).each(
            (i, el) => {
              // if title is the same, add area to overlay map
              if (il.COPagePres.iim_area[el.id].tr_nr == area_tr_nr) {
                coords = $(el).attr('coords');
                // fix coords
                switch ($(el).attr('shape').toLowerCase()) {
                  case 'rect':
                    c = coords.split(',');
                    coords = String(`${parseInt(c[0], 10) - ovx},${
                      parseInt(c[1], 10) - ovy},${
                      parseInt(c[2], 10) - ovx},${
                      parseInt(c[3], 10) - ovy}`);
                    break;

                  case 'poly':
                    c = coords.split(',');
                    coords = '';
                    var sep = '';
                    for (j in c) {
                      if (j % 2 == 0) {
                        coords = coords + sep + parseInt(c[j] - ovx, 10);
                      } else {
                        coords = coords + sep + parseInt(c[j] - ovy, 10);
                      }
                      sep = ',';
                    }
                    break;

                  case 'circle':
                    c = coords.split(',');
                    coords = String(`${parseInt(c[0], 10) - ovx},${
                      parseInt(c[1], 10) - ovy},${
                      parseInt(c[2], 10)}`);
                    break;
                }

                // set shape and coords
                $(`area#iim_ov_area_${tr.tr_id}`).attr('coords', coords);
                $(`area#iim_ov_area_${tr.tr_id}`).attr('shape', $(el).attr('shape'));

                // add mouse event listeners
                k2 = k;
                i2 = `iim_ov_${tr.tr_id}`;
                tr2 = tr.tr_id;
  								$(`area#iim_ov_area_${tr.tr_id}`).mouseover(
  									() => { il.COPagePres.overOvArea(k2, true, i2); },
                );
  								$(`area#iim_ov_area_${tr.tr_id}`).mouseout(
  									() => { il.COPagePres.overOvArea(k2, false, i2); },
                );
  								$(`area#iim_ov_area_${tr.tr_id}`).click(
  									(e) => { il.COPagePres.clickOvArea(e, tr2); },
                );
              }
            },
          );
        }
      }
    }
  },

  /**
	 * Leave a base image map area: hide corresponding images
	 */
  outBaseArea(e) {
    const area_tr_nr = il.COPagePres.iim_area[e.target.id].tr_nr;
    const { iim_id } = il.COPagePres.iim_area[e.target.id];
    il.COPagePres.handleOutEvent(iim_id, area_tr_nr);
  },

  /**
	 * Leave a base image map area: hide corresponding images
	 */
  handleOutEvent(iim_id, area_tr_nr) {
    let k; let
      tr;

    if (this.dragging) {
      return;
    }

    for (k in il.COPagePres.iim_trigger) {
      tr = il.COPagePres.iim_trigger[k];
      if (tr.nr == area_tr_nr && tr.iim_id == iim_id
				&& (il.COPagePres.iim_trigger[k].over_ov_area == null
					|| !il.COPagePres.iim_trigger[k].over_ov_area
				)) {
        $(`img#iim_ov_${tr.tr_id}`).css('display', 'none');
      }
    }
  },

  /**
	 * Triggered by mouseover/out on imagemap of overlay image
	 */
  overOvArea(k, value, ov_id) {
    if (this.dragging) {
      return;
    }

    il.COPagePres.iim_trigger[k].over_ov_area = value;
    if (value) {
      $(`img#${ov_id}`).css('display', '');
    } else {
      $(`img#${ov_id}`).css('display', 'none');
    }
  },

  /**
	 * A marker is clicked
	 */
  clickMarker(e) {
    let k; let tr;
    const marker_tr_nr = il.COPagePres.iim_marker[e.target.id].tr_nr;
    const { iim_id } = il.COPagePres.iim_marker[e.target.id];

    if (il.COPagePres.iim_marker[e.target.id].edit_mode == '1') {
      return;
    }

    if (this.dragging) {
      return;
    }

    // iterate through the triggers and search the correct one
    for (k in il.COPagePres.iim_trigger) {
      tr = il.COPagePres.iim_trigger[k];
      if (tr.nr == marker_tr_nr && tr.iim_id == iim_id) {
        il.COPagePres.handleAreaClick(e, tr.tr_id);
      }
    }
  },

  /**
	 * A base image map area is clicked
	 */
  clickBaseArea(e) {
    let k; let tr;
    const area_tr_nr = il.COPagePres.iim_area[e.target.id].tr_nr;
    const { iim_id } = il.COPagePres.iim_area[e.target.id];

    if (this.dragging) {
      return;
    }

    // iterate through the triggers and search the correct one
    for (k in il.COPagePres.iim_trigger) {
      tr = il.COPagePres.iim_trigger[k];
      if (tr.nr == area_tr_nr && tr.iim_id == iim_id) {
        il.COPagePres.handleAreaClick(e, tr.tr_id);
      }
    }
  },

  /**
	 * Handle area click (triggered by base or overlay image map area)
	 */
  handleAreaClick(e, tr_id) {
    const areaEl = e.target;
    console.log(areaEl);
    console.log(tr_id);
    console.log(il.COPagePres.iim_trigger);

    const tr = il.COPagePres.iim_trigger[tr_id];
    const el = document.getElementById(`iim_popup_${tr.iim_id}_${tr.popup_nr}`);
    let base; let pos; let x; let
      y;

    if (el == null || this.dragging) {
      e.preventDefault();
      return;
    }

    const nr = tr.popup_nr;
    const popupEl = document.querySelector(`[data-copg-cont-type='iim-popup'][data-copg-popup-nr='${nr}']`);

    const button = e.target;
    const tooltip = popupEl;

    if (popupEl) {
      if (popupEl.style.display == 'none') {
        popupEl.style.display = '';
      } else {
        popupEl.style.display = 'none';
      }
      e.preventDefault();
      return;

      const { signalId } = popupEl.dataset;
      console.log('TRIGGER');
      $(document).trigger(
        signalId,
        {
          id: signalId,
          event: 'click',
          triggerer: $(areaEl),
          options: JSON.parse('[]'),
        },
      );
      if (tr.popup_initialized == null) {
        tr.popup_initialized = true;
        /*
				console.log("TRIGGER");
				$(document).trigger(signalId,
					{
						'id' : signalId, 'event' : 'click',
						'triggerer' : $(areaEl),
						'options' : JSON.parse('[]')
					}
				); */
      }
    }

    // on first time we need to initialize content overlay

    if (tr.popup_initialized == null) {
      tr.popup_initialized = true;
      /*
			if (popupEl) {
				$(document).trigger(signalId,
					{
						'id': signalId, 'event': 'click',
						'triggerer': $(areaEl),
						'options': JSON.parse('[]')
					}
				);
			} */
      /* il.Overlay.add("iim_popup_" + tr.iim_id + "_" + tr.popup_nr,
				{"yuicfg":{"visible":false,"fixedcenter":false},
				"auto_hide":false}); */
    }

    // show the overlay
    /*
		base = $("img#base_img_" + il.COPagePres.iim_trigger[tr_id].iim_id);
		pos = base.offset();
		x = pos.left + parseInt(il.COPagePres.iim_trigger[tr_id].popx, 10);
		y = pos.top + parseInt(il.COPagePres.iim_trigger[tr_id].popy, 10);
		il.Overlay.setWidth("iim_popup_" + tr.iim_id + "_" + tr.popup_nr, il.COPagePres.iim_trigger[tr_id].popwidth);
		il.Overlay.setHeight("iim_popup_" + tr.iim_id + "_" + tr.popup_nr, il.COPagePres.iim_trigger[tr_id].popheight);
		il.Overlay.toggle(e, "iim_popup_" + tr.iim_id + "_" + tr.popup_nr, null, false, null, null, "click");
		il.Overlay.setX("iim_popup_" + tr.iim_id + "_" + tr.popup_nr, x);
		il.Overlay.setY("iim_popup_" + tr.iim_id + "_" + tr.popup_nr, y); */

    e.preventDefault();
  },

  /**
	 * A overlay image map area is clicked
	 */
  clickOvArea(e, tr_id) {
    il.COPagePres.handleAreaClick(e, tr_id);
  },

  addIIMTrigger(tr) {
    this.iim_trigger[tr.tr_id] = tr;
  },

  addIIMArea(a) {
    this.iim_area[a.area_id] = a;
  },

  addIIMPopup(p) {
    this.iim_popup[p.pop_id] = p;
  },

  addIIMMarker(m) {
    let base; let pos; let mark; let mx; let
      my;

    this.iim_marker[m.m_id] = m;
    const p = this.fixMarkerPosition;
    p(m);
    window.setTimeout(() => {
      p(m);
    }, 500);
  },

  fixMarkerPosition(m) {
    let base; let pos; let mark; let mx; let
      my;
    base = $(`img#base_img_${m.iim_id}`);
    pos = base.offset();
    mark = $(`a#${m.m_id}`);
    // display the marker at the correct position
    mark.css('position', 'absolute');
    mx = pos.left + parseInt(m.markx, 10);
    my = pos.top + parseInt(m.marky, 10);
    mark.css('display', '');
    il.Overlay.setX(m.m_id, mx);
    il.Overlay.setY(m.m_id, my);
  },

  fixMarkerPositions() {
    let m; let k; let base; let pos; let mark; let mx; let
      my;

    for (k in il.COPagePres.iim_marker) {
      m = il.COPagePres.iim_marker[k];
      this.fixMarkerPosition(m);
    }
  },

  /**
	 * Make marker draggable
	 */
  startDraggingMarker(tr_nr) {
    let k; let
      mark;

    this.dragging = true;
    for (k in il.COPagePres.iim_marker) {
      if (il.COPagePres.iim_marker[k].tr_nr == tr_nr) {
        mark = il.COPagePres.iim_marker[k];
        $(`a#${il.COPagePres.iim_marker[k].m_id}`).css('display', '');
        il.COPagePres.fixMarkerPositions();
        $(`a#${il.COPagePres.iim_marker[k].m_id}`).draggable({
          drag(event, ui) {
            let base; let bpos; let marker; let mpos; let
              position;

            base = $(`img#base_img_${mark.iim_id}`);
            bpos = base.position();
            marker = $(`a#${mark.m_id}`);
            mpos = marker.position();
            // position = (Math.round(mpos.left) - Math.round(bpos.left)) + "," +
            //	(Math.round(mpos.top) - Math.round(bpos.top));
            position = `${Math.round(marker.offset().left) - Math.round(base.offset().left)},${
              Math.round(marker.offset().top) - Math.round(base.offset().top)}`;

            $(`input#markpos_${mark.tr_nr}`).attr('value', position);
          },
        });

        il.COPagePres.initDragToolbar();
      } else {
        $(`a#${il.COPagePres.iim_marker[k].m_id}`).css('display', 'none');
      }
    }
  },

  stopDraggingMarker() {
    this.dragging = false;
  },

  /**
	 * Make overlay draggable
	 */
  startDraggingOverlay(tr_nr) {
    let k; let trigger; let dtr; let ov; let base; let bpos; let ovx; let
      ovy;

    this.dragging = true;

    for (k in il.COPagePres.iim_trigger) {
      trigger = il.COPagePres.iim_trigger[k];

      if (trigger.nr == tr_nr) {
        dtr = trigger;
        ov = $(`img#iim_ov_${dtr.tr_id}`);

        // remove map for dragging
        ov.attr('usemap', '');

        il.COPagePres.initDragToolbar();

        base = $(`img#base_img_${dtr.iim_id}`);
        bpos = base.offset();
        ovx = bpos.left + parseInt(dtr.ovx, 10);
        ovy = bpos.top + parseInt(dtr.ovy, 10);
        ov.css('display', '');
        ov.css('position', 'absolute');
        il.Overlay.setX(`iim_ov_${dtr.tr_id}`, ovx);
        il.Overlay.setY(`iim_ov_${dtr.tr_id}`, ovy);

        dtr = trigger;
        ov.draggable({
          stop(event, ui) {
            let ovpos; let
              position;

            ovpos = ov.position();
            position = `${Math.round(ov.offset().left) - Math.round(base.offset().left)},${
              Math.round(ov.offset().top) - Math.round(base.offset().top)}`;

            $(`input#ovpos_${dtr.nr}`).attr('value', position);
          },
        });
      }
    }
  },

  /**
	 * Make popup draggable
	 */
  startDraggingPopup(tr_nr) {
    let i; let k; let dtr; let cpop; let pdummy; let base; let bpos; let popx; let
      popy;

    this.dragging = true;

    // get correct trigger
    for (k in il.COPagePres.iim_trigger) {
      if (il.COPagePres.iim_trigger[k].nr == tr_nr) {
        dtr = il.COPagePres.iim_trigger[k];

        // get correct popup
        for (i in il.COPagePres.iim_popup) {
          if (il.COPagePres.iim_popup[i].nr
						== il.COPagePres.iim_trigger[k].popup_nr) {
            cpop = il.COPagePres.iim_popup[i];
            pdummy = document.getElementById('popupdummy');
            if (pdummy == null) {
              $('div#il_center_col').append('<div id="popupdummy" class="ilc_iim_ContentPopup"></div>');
              pdummy = $('div#popupdummy');
            } else {
              pdummy = $('div#popupdummy');
            }

            il.COPagePres.initDragToolbar();

            base = $(`img#base_img_${cpop.iim_id}`);
            bpos = base.offset();
            popx = bpos.left + parseInt(dtr.popx, 10);
            popy = bpos.top + parseInt(dtr.popy, 10);
            pdummy.css('position', 'absolute');
            pdummy.css('width', dtr.popwidth);
            pdummy.css('height', dtr.popheight);
            pdummy.css('display', '');
            il.Overlay.setX('popupdummy', popx);
            il.Overlay.setY('popupdummy', popy);

            pdummy.draggable({
              stop(event, ui) {
                let pdpos; let
                  position;

                pdpos = pdummy.position();
                position = `${Math.round(pdummy.offset().left) - Math.round(base.offset().left)},${
                  Math.round(pdummy.offset().top) - Math.round(base.offset().top)}`;
                $(`input#poppos_${dtr.nr}`).attr('value', position);
              },
            });
          }
        }
      }
    }
  },

  /**
	 * Init drag toolbar
	 */
  initDragToolbar() {
    // show the toolbar
    $('#drag_toolbar').removeClass('ilNoDisplay');
    this.fixMarkerPositions();
    $('#save_pos_button').click(() => {
      $('input#update_tr_button').trigger('click');
    });
  },

  //
  // Question Overviews
  //

  qover: {},
  ganswer_data: {},

  addQuestionOverview(conf) {
    this.qover[conf.id] = conf;
  },

  updateQuestionOverviews() {
    const correct = {};
    const incorrect = {};
    let correct_cnt = 0;
    let incorrect_cnt = 0;
    let answered_correctly; let index; let k; let i; let ov_el; let ul; let j; let
      qtext;

    if (typeof questions === 'undefined') {
      // #17532 - question overview does not work in copage editor / preview
      for (i in this.qover) {
        ov_el = $(`div#${this.qover[i].div_id}`);
        $(ov_el).addClass('ilBox');
        $(ov_el).css('margin', '5px');
        ov_el.empty();
        ov_el.append(`<div class="il_Description_no_margin">${ilias.questions.txt.ov_preview}</div>`);
      }

      return;
    }

    for (k in questions) {
      answered_correctly = true;
      index = parseInt(k, 10);
      if (!isNaN(index)) {
        if (!answers[index]) {
          answered_correctly = false;
        } else if (answers[index].passed != true) {
          answered_correctly = false;
        }
        if (!answered_correctly) {
          incorrect[k] = k;
          incorrect_cnt++;
        } else {
          correct[k] = k;
          correct_cnt++;
        }
      }
    }

    // iterate all question overview elements
    for (i in this.qover) {
      ov_el = $(`div#${this.qover[i].div_id}`);

      // remove all children
      ov_el.empty();

      // show success message, if all questions have been answered
      if (incorrect_cnt == 0) {
        ov_el.attr('class', 'ilc_qover_Correct');
        ov_el.append(
          ilias.questions.txt.ov_all_correct,
        );
      } else {
        ov_el.attr('class', 'ilc_qover_Incorrect');
        // show message including of number of not
        // correctly answered questions
        if (this.qover[i].short_message == 'y') {
          ov_el.append(`<div class="ilc_qover_StatusMessage">${
            ilias.questions.txt.ov_some_correct.split('[x]').join(String(correct_cnt))
              .split('[y]').join(String(incorrect_cnt + correct_cnt))
          }</div>`);
        }

        if (this.qover[i].list_wrong_questions == 'y') {
          ov_el.append(
            `<div class="ilc_qover_WrongAnswersMessage">${
						 ilias.questions.txt.ov_wrong_answered}:` + '</div>',
          );

          // list all incorrect answered questions
          ov_el.append('<ul class="ilc_list_u_BulletedList"></ul>');
          ul = $(`div#${this.qover[i].div_id} > ul`);
          for (j in incorrect) {
            qtext = questions[j].question;

            if (questions[j].type == 'assClozeTest') {
              qtext = questions[j].title;
            }

            ul.append(
              '<li class="ilc_list_item_StandardListItem">'
							+ `<a href="#" onclick="return il.COPagePres.jumpToQuestion('${j}');" class="ilc_qoverl_WrongAnswerLink">${qtext}</a>`
							+ '</li>',
            );
          }
        }
      }
    }
  },

  // jump to a question
  jumpToQuestion(qid) {
    if (typeof pager !== 'undefined') {
      pager.jumpToElement(`container${qid}`);
    }
    return false;
  },

  setGivenAnswerData(data) {
    ilCOPagePres.ganswer_data = data;
  },

  //
  // Map area functions
  //

  // init map areas
  initMapAreas() {
    $('img[usemap^="#map_il_"][class!="ilIim"]').maphilight({ neverOn: true });
  },

  /// /
  /// / Handle advanced content
  /// /
  showadvcont: true,
  initAdvancedContent() {
    const c = $('div.ilc_section_AdvancedKnowledge');
    const b = $('#ilPageShowAdvContent'); let
      cookiePos;
    if (c.length > 0 && b.length > 0) {
      cookiePos = document.cookie.indexOf('pg_hideadv=');
      if (cookiePos > -1 && document.cookie.charAt(cookiePos + 11) == 1) {
        this.showadvcont = false;
      }

      $('#ilPageShowAdvContent').css('display', 'block');
      if (il.COPagePres.showadvcont) {
        $('div.ilc_section_AdvancedKnowledge').css('display', '');
        $('#ilPageShowAdvContent > span:nth-child(1)').css('display', 'none');
      } else {
        $('div.ilc_section_AdvancedKnowledge').css('display', 'none');
        $('#ilPageShowAdvContent > span:nth-child(2)').css('display', 'none');
      }
      $('#ilPageShowAdvContent').click(() => {
        if (il.COPagePres.showadvcont) {
          $('div.ilc_section_AdvancedKnowledge').css('display', 'none');
          $('#ilPageShowAdvContent > span:nth-child(1)').css('display', '');
          $('#ilPageShowAdvContent > span:nth-child(2)').css('display', 'none');
          il.COPagePres.showadvcont = false;
          document.cookie = 'pg_hideadv=1';
        } else {
          $('div.ilc_section_AdvancedKnowledge').css('display', '');
          $('#ilPageShowAdvContent > span:nth-child(1)').css('display', 'none');
          $('#ilPageShowAdvContent > span:nth-child(2)').css('display', '');
          il.COPagePres.showadvcont = true;
          document.cookie = 'pg_hideadv=0';
        }
        return false;
      });
    }
  },

  /// /
  /// / Audio/Video
  /// /

  initAudioVideo(acc_el) {
    let $elements;
    if (acc_el) {
      $elements = $(acc_el).find('video.ilPageVideo,audio.ilPageAudio');
    } else {
      $elements = $('video.ilPageVideo,audio.ilPageAudio');
    }

    if ($elements.mediaelementplayer) {
      $elements.each((i, el) => {
        let def; let
          cfg;

        def = $(el).find("track[default='default']").first().attr('srclang');
        cfg = {};
        if (def != '') {
          cfg.startLanguage = def;
        }
        $(el).mediaelementplayer(cfg);
      });
    }
  },

  setFullscreenModalShowSignal(signal, suffix) {
    il.COPagePres.fullscreen_signal = signal;
    il.COPagePres.fullscreen_suffix = suffix;
    $(`#il-copg-mob-fullscreen${suffix}`).closest('.modal').on('shown.bs.modal', () => {
      il.COPagePres.resizeFullScreenModal(suffix);
    }).on('hidden.bs.modal', () => {
      $(`#il-copg-mob-fullscreen${suffix}`).attr('src', '');
    });
  },

  inIframe() {
    try {
      return window.self !== window.top;
    } catch (e) {
      return true;
    }
  },

  openFullScreenModal(target) {
    // see 32198
    if (il.COPagePres.inIframe()) {
      window.parent.il.COPagePres.openFullScreenModal(target);
      return;
    }
    $(`#il-copg-mob-fullscreen${il.COPagePres.fullscreen_suffix}`).attr('src', target);
    // workaround for media pool full screen view
    $('#ilMepPreviewContent').attr('src', target);
    if (il.COPagePres.fullscreen_signal) {
      $(document).trigger(il.COPagePres.fullscreen_signal, {
        id: il.COPagePres.fullscreen_signal,
        event: 'click',
        triggerer: $(document),
        options: JSON.parse('[]'),
      });
    }
  },

  resizeFullScreenModal(suffix) {
    const vp = il.Util.getViewportRegion();
    const ifr = il.Util.getRegion(`#il-copg-mob-fullscreen${suffix}`);
    $('.il-copg-mob-fullscreen').css('height', `${vp.height - ifr.top + vp.top - 120}px`);
  },

};
il.Util.addOnLoad(() => { il.COPagePres.init(); });
