/**
 * Frontend Member Dashboard JavaScript routines.
 *
 * Handles client-side tab navigation, AJAX loading of course and workshop data,
 * template rendering for cards, grids, and profile update processing.
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// Verify we are on the dashboard page.
		if ($('#ggm-dash').length === 0) {
			return;
		}

		var dashboardData = null;
		var razorpayLoadPromise = null;
		var quickBuyFlowActive = false;
		var dashboardQuery = new URLSearchParams(window.location.search);
		var resumeWorkshopId = parseInt(dashboardQuery.get('ggm_enroll_workshop') || '0', 10) || 0;
		var resumePayment = dashboardQuery.get('ggm_resume_payment') === '1';
		var resumeContributionOption = dashboardQuery.get('ggm_contribution_option') || '';
		var resumeHandled = false;

		// Fit the desktop dashboard below any WordPress or theme header. Only the
		// tab content scrolls; the sidebar and dashboard top bar remain stationary.
		function syncDashboardViewportHeight() {
			var dashboard = document.getElementById('ggm-dash');
			if (!dashboard) return;

			if (window.matchMedia('(max-width: 768px)').matches) {
				dashboard.style.removeProperty('--ggm-dashboard-viewport-height');
				return;
			}

			var viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
			var dashboardTop = Math.max(0, dashboard.getBoundingClientRect().top);
			var availableHeight = Math.max(400, Math.floor(viewportHeight - dashboardTop));
			dashboard.style.setProperty('--ggm-dashboard-viewport-height', availableHeight + 'px');
		}

		syncDashboardViewportHeight();
		window.addEventListener('resize', syncDashboardViewportHeight, { passive: true });
		if (window.visualViewport) {
			window.visualViewport.addEventListener('resize', syncDashboardViewportHeight, { passive: true });
		}

		// Task 10 — WhatsApp Number country code selector, default India.
		var GGM_COUNTRY_CODES_FALLBACK = [
			{ code: '+91',  label: '🇮🇳 +91' },
			{ code: '+1',   label: '🇺🇸 +1' },
			{ code: '+44',  label: '🇬🇧 +44' },
			{ code: '+971', label: '🇦🇪 +971' },
			{ code: '+966', label: '🇸🇦 +966' },
			{ code: '+61',  label: '🇦🇺 +61' },
			{ code: '+65',  label: '🇸🇬 +65' },
			{ code: '+49',  label: '🇩🇪 +49' },
			{ code: '+33',  label: '🇫🇷 +33' },
			{ code: '+64',  label: '🇳🇿 +64' }
		];
		var GGM_COUNTRY_CODES = (window.ggm_public && Array.isArray(window.ggm_public.country_codes) && window.ggm_public.country_codes.length)
			? window.ggm_public.country_codes.map(function(country) {
				return { code: country.dial, dial: country.dial, iso: country.iso, name: country.name || country.iso, flag: country.flag || '' };
			})
			: GGM_COUNTRY_CODES_FALLBACK;

		// ─────────────────────────────────────────────────────────────────────
		// 1. Tab Switching Initialization
		// ─────────────────────────────────────────────────────────────────────
		function initTabs() {
			// Sidebar Menu Clicks (Desktop)
			$('.ggm-dash-nav').on('click', '.ggm-dash-nav-item', function() {
				var tabId = $(this).data('tab');
				switchTab(tabId);
			});

			// Bottom Nav Clicks (Mobile)
			$('.ggm-bottom-nav').on('click', '.ggm-bn-item', function() {
				var tabId = $(this).data('tab');
				switchTab(tabId);
			});

			// Handle Store link redirection trigger
			$(document).on('click', '.ggm-trigger-courses-tab', function(e) {
				e.preventDefault();
				switchTab('courses');
			});

			$(document).on('click', '.ggm-trigger-workshops-tab', function(e) {
				e.preventDefault();
				switchTab('free');
			});

			// A shareable server-side link uses ?ggm_tab=health so the target
			// survives the login redirect. Normal in-dashboard navigation keeps
			// using the existing #tab-* hashes.
			var queryTab = new URLSearchParams(window.location.search).get('ggm_tab');
			var hash = window.location.hash;
			if (queryTab && $('#tab-' + queryTab).length) {
				switchTab(queryTab);
			} else if (hash && hash.indexOf('#tab-') === 0) {
				var targetTab = hash.replace('#tab-', '');
				switchTab(targetTab);
			} else {
				switchTab('home');
			}
		}

		function switchTab(tabId) {
			if (!tabId) return;
			if ('courses' !== tabId) {
				$('#ggm-dash').removeClass('ggm-course-mode');
			}

			// Sync active classes on nav elements
			$('.ggm-dash-nav-item').removeClass('active');
			$('.ggm-dash-nav-item[data-tab="' + tabId + '"]').addClass('active');

			$('.ggm-bottom-nav .ggm-bn-item').removeClass('active');
			$('.ggm-bn-item[data-tab="' + tabId + '"]').addClass('active');

			// Sync active panels
			$('.ggm-main .ggm-tab').removeClass('active');
			$('#tab-' + tabId).addClass('active');
			if ('disease' === tabId) {
				window.setTimeout(initDiseaseDashboardCards, 0);
			}

			window.location.hash = 'tab-' + tabId;
		}

		function initDashboardFormPopup() {
			var $host = $('#tab-health .ggm-dashboard-assigned-forms').first();
			var $banner = $('#ggm-topbar-pending-banner');
			var $bannerBtn = $('#ggm-topbar-pending-btn');

			function computePendingForms() {
				return $host.find('.ggm-built-form[data-dashboard-auto-popup="1"] .ggm-builder-public-form').map(function () {
					return $(this).closest('.ggm-built-form')[0];
				}).get();
			}

			var pendingForms = computePendingForms();
			var popupOpen = false;

			function updateBanner() {
				$banner.prop('hidden', pendingForms.length === 0);
			}

			updateBanner();

			// Assigned health forms are rendered in the Health Information tab.
			// Keep them in that page instead of moving their DOM into an auto-open modal.
			$bannerBtn.on('click', function () {
				if (!pendingForms.length) {
					return;
				}
				switchTab('health');
				window.setTimeout(function () {
					var target = $host.find('.ggm-built-form[data-dashboard-auto-popup="1"]').first()[0] || $host[0];
					if (target) {
						target.scrollIntoView({ behavior: 'smooth', block: 'start' });
						var firstField = target.querySelector('input:not([type="hidden"]), select, textarea, button');
						if (firstField) {
							firstField.focus({ preventScroll: true });
						}
					}
				}, 50);
			});

			$host.on('ggmFormSubmitted', function () {
				pendingForms = computePendingForms();
				updateBanner();
			});

			return;

			$bannerBtn.on('click', function () {
				if (!pendingForms.length || popupOpen) {
					return;
				}
				openPopup();
			});

			if (!pendingForms.length) {
				return;
			}

			openPopup();

			function openPopup() {
			popupOpen = true;
			var previousFocus = document.activeElement;
			var dialogLabel = $host.attr('data-popup-label') || 'Health information forms';
			var closeLabel = $host.attr('data-popup-close-label') || 'Close health form popup';
			var continueLabel = $host.attr('data-popup-continue-label') || 'Continue to next form';
			var doneLabel = $host.attr('data-popup-done-label') || 'Close';
			var labelId = 'ggm-dashboard-form-modal-title-' + Date.now().toString(36);
			var $overlay = $('<div class="ggm-dashboard-form-modal"></div>');
			var $dialog = $('<div class="ggm-dashboard-form-modal-dialog" role="dialog" aria-modal="true"></div>').attr('aria-labelledby', labelId);
			var $title = $('<h2 class="ggm-dashboard-form-modal-title"></h2>').attr('id', labelId).text(dialogLabel);
			var $close = $('<button type="button" class="ggm-dashboard-form-modal-close"><span aria-hidden="true">&times;</span></button>').attr('aria-label', closeLabel);
			var $body = $('<div class="ggm-dashboard-form-modal-body"></div>');
			var backgroundState = [];
			var currentForm = null;
			var closed = false;

			function moveFormToPopup(form) {
				var marker = document.createComment('ggm-dashboard-form-location');
				form.parentNode.insertBefore(marker, form);
				form.ggmDashboardFormMarker = marker;
				currentForm = form;
				$body.empty().append(form);
				$overlay.removeClass('has-submitted-form');
				$body.scrollTop(0);
			}

			function restoreForm(form) {
				if (!form) {
					return;
				}
				var marker = form.ggmDashboardFormMarker;
				if (marker && marker.parentNode) {
					marker.parentNode.insertBefore(form, marker);
					marker.parentNode.removeChild(marker);
				}
				delete form.ggmDashboardFormMarker;
			}

			function restoreBackground() {
				backgroundState.forEach(function (state) {
					if (state.hadInert) {
						state.element.setAttribute('inert', '');
					} else {
						state.element.removeAttribute('inert');
					}
					if (null === state.ariaHidden) {
						state.element.removeAttribute('aria-hidden');
					} else {
						state.element.setAttribute('aria-hidden', state.ariaHidden);
					}
				});
				backgroundState = [];
			}

			function closePopup() {
				if (closed) {
					return;
				}
				closed = true;
				popupOpen = false;
				restoreForm(currentForm);
				currentForm = null;
				$(document).off('.ggmDashboardFormModal');
				$('body').removeClass('ggm-dashboard-form-modal-open');
				restoreBackground();
				$overlay.removeClass('is-open').remove();
				updateBanner();
				var focusTarget = previousFocus && previousFocus !== document.body && document.contains(previousFocus)
					? previousFocus
					: $('.ggm-dash-nav-item[data-tab="health"]:visible, .ggm-bn-item[data-tab="health"]:visible').first()[0];
				if (focusTarget && typeof focusTarget.focus === 'function') {
					focusTarget.focus();
				}
			}

			function showNextForm() {
				if (!pendingForms.length) {
					closePopup();
					return;
				}
				moveFormToPopup(pendingForms[0]);
				window.requestAnimationFrame(function () {
					$close.trigger('focus');
				});
			}

			function handleFormSubmitted(event) {
				var submittedForm = $(event.target).closest('.ggm-built-form')[0];
				if (!submittedForm || submittedForm !== currentForm) {
					return;
				}
				pendingForms = pendingForms.filter(function (form) {
					return form !== submittedForm;
				});
				updateBanner();
				$overlay.addClass('has-submitted-form');
				var $action = $('<button type="button" class="ggm-btn ggm-btn-primary ggm-dashboard-form-modal-action"></button>')
					.text(pendingForms.length ? continueLabel : doneLabel);
				$action.on('click', function () {
					restoreForm(currentForm);
					currentForm = null;
					if (pendingForms.length) {
						showNextForm();
					} else {
						closePopup();
					}
				});
				$body.append($action);
			}

			$close.on('click', closePopup);
			$overlay.on('click', function (event) {
				if (event.target === $overlay[0]) {
					closePopup();
				}
			});
			$(document).on('keydown.ggmDashboardFormModal', function (event) {
				if ('Escape' === event.key) {
					event.preventDefault();
					closePopup();
					return;
				}
				if ('Tab' !== event.key) {
					return;
				}
				var $focusable = $dialog.find('a[href], button:not(:disabled), input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])').filter(':visible');
				if (!$focusable.length) {
					event.preventDefault();
					$close.trigger('focus');
					return;
				}
				var first = $focusable[0];
				var last = $focusable[$focusable.length - 1];
				if (!$.contains($dialog[0], document.activeElement)) {
					event.preventDefault();
					first.focus();
				} else if (event.shiftKey && document.activeElement === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
					first.focus();
				}
			});
			$(document).on('focusin.ggmDashboardFormModal', function (event) {
				if (!closed && event.target !== $dialog[0] && !$.contains($dialog[0], event.target)) {
					$close.trigger('focus');
				}
			});
			$(document).on('focusout.ggmDashboardFormModal', function () {
				window.setTimeout(function () {
					if (!closed && document.activeElement !== $dialog[0] && !$.contains($dialog[0], document.activeElement)) {
						$close.trigger('focus');
					}
				}, 0);
			});

			$overlay[0].addEventListener('ggmFormSubmitted', handleFormSubmitted);
			$dialog.append($title, $close, $body);
			$overlay.append($dialog);
			showNextForm();
			$('body').addClass('ggm-dashboard-form-modal-open').append($overlay);
			$('body').children().not($overlay).not('script, style, link').each(function () {
				backgroundState.push({
					element: this,
					hadInert: this.hasAttribute('inert'),
					ariaHidden: this.getAttribute('aria-hidden')
				});
				this.setAttribute('inert', '');
				this.setAttribute('aria-hidden', 'true');
			});
			window.requestAnimationFrame(function () {
				$overlay.addClass('is-open');
				$close.trigger('focus');
			});
			}
		}

		// Logout confirmation handler — use fresh nonce URL from localized data.
		$('#ggm-dash-logout-btn').on('click', function(e) {
			e.preventDefault();
			if (confirm('Are you sure you want to log out?')) {
				var logoutHref = (typeof ggm_public !== 'undefined' && ggm_public.logout_url)
					? ggm_public.logout_url
					: $(this).attr('href');
				window.location.href = logoutHref;
			}
		});

		// ─────────────────────────────────────────────────────────────────────
		// 2. Fetch and Load Dashboard Data
		// ─────────────────────────────────────────────────────────────────────
		function dashboardMessage(key, fallback) {
			return (typeof window.ggm_public === 'object' && window.ggm_public && window.ggm_public[key]) || fallback;
		}

		function showDashboardLoadError(message) {
			var html = '<div class="ggm-error-alert ggm-dashboard-load-error">' +
				'<p>' + escapeHtml(message) + '</p>' +
				'<button type="button" class="ggm-btn ggm-dashboard-retry">' + escapeHtml(dashboardMessage('dashboard_retry', 'Try again')) + '</button>' +
				'</div>';
			$('#ggm-home-course, #ggm-courses-list, #ggm-free-list').html(html);
		}

		$(document).on('click', '.ggm-dashboard-retry', function() {
			loadData();
		});

		function loadData() {
			var $loader = $('.ggm-dash-loader');
			var config = (typeof window.ggm_public === 'object' && window.ggm_public) ? window.ggm_public : {};
			var ajaxUrl = config.ajax_url || config.ajaxurl || '';
			$loader.stop(true, true).css('display', 'flex').attr('aria-hidden', 'false');
			$('.ggm-dashboard-retry').prop('disabled', true);

			if (!ajaxUrl || !config.nonce) {
				$loader.hide().attr('aria-hidden', 'true');
				showDashboardLoadError(dashboardMessage('dashboard_config_error', 'Dashboard configuration is unavailable. Please refresh the page.'));
				return;
			}

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'ggm_dashboard_ajax',
					nonce: config.nonce,
					ggm_currency: window.ggmSelectedCurrency || config.currency || ''
				},
				dataType: 'json',
				timeout: 30000,
				success: function(response) {
					if (response && response.success && response.data) {
						dashboardData = response.data;
						try {
							renderDashboard(dashboardData);
							resumeWorkshopEnrollment();
						} catch (renderError) {
							if (window.console && typeof window.console.error === 'function') {
								window.console.error('GGM dashboard render failed:', renderError);
							}
							showDashboardLoadError(dashboardMessage('dashboard_load_error', 'Failed to render dashboard data. Please try again.'));
						}
					} else {
						var responseMessage = response && response.data && response.data.message;
						showDashboardLoadError(responseMessage || dashboardMessage('dashboard_load_error', 'Error loading dashboard data.'));
					}
				},
				error: function(xhr, status) {
					var responseMessage = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
					var fallback = status === 'timeout'
						? dashboardMessage('dashboard_timeout_error', 'The dashboard is taking too long to respond. Please try again.')
						: dashboardMessage('dashboard_load_error', 'Failed to load dashboard data. Please try again.');
					showDashboardLoadError(responseMessage || fallback);
				},
				complete: function() {
					$loader.fadeOut(200).attr('aria-hidden', 'true');
					$('.ggm-dashboard-retry').prop('disabled', false);
				}
			});
		}

		// ─────────────────────────────────────────────────────────────────────
		// 3. Render Dashboard Views
		// ─────────────────────────────────────────────────────────────────────
		function renderDashboard(data) {
			renderHome(data);
			renderCourses(data);
			renderWorkshops(data);
			renderProfile(data.profile);
		}

		// 3a. Home View
		function renderHome(d) {
			var firstCourse = d.enrolled_courses.length > 0 ? d.enrolled_courses[0] : null;

			var allWorkshops = (d.free_workshops || []).concat(d.paid_workshops || []);
			var accessibleWorkshops = allWorkshops.filter(function (w) { return w.has_access; });
			var firstWorkshop = accessibleWorkshops.length > 0 ? accessibleWorkshops[0] : null;

			var html = '';

			html += '<div class="ggm-home-two-column">';

			// Active/Last Course section
			html += '<div class="ggm-home-section">';
			html += '  <h3 class="ggm-section-title">Your Current Course</h3>';

			if (firstCourse) {
				html += '  <div class="ggm-cards-grid single-item">';
				html +=      courseCard(firstCourse);
				html += '  </div>';
			} else {
				html += '  <div class="ggm-empty">';
				html += '    <div class="ggm-empty-icon">📂</div>';
				html += '    <h3>No Enrolled Courses</h3>';
				html += '    <p>Enroll in a course to unlock structured yoga and wellness lessons.</p>';
				html += '    <button class="ggm-btn ggm-trigger-courses-tab">Explore Courses</button>';
				html += '  </div>';
			}
			html += '</div>';

			// Active/Last Workshop section
			html += '<div class="ggm-home-section">';
			html += '  <h3 class="ggm-section-title">Your Workshops</h3>';

			if (firstWorkshop) {
				html += '  <div class="ggm-cards-grid single-item">';
				html +=      workshopCard(firstWorkshop);
				html += '  </div>';
			} else {
				html += '  <div class="ggm-empty">';
				html += '    <div class="ggm-empty-icon">🎓</div>';
				html += '    <h3>No Workshops Yet</h3>';
				html += '    <p>Register for a free workshop or unlock a premium one to see it here.</p>';
				html += '    <button class="ggm-btn ggm-trigger-workshops-tab">Explore Workshops</button>';
				html += '  </div>';
			}
			html += '</div>';
			html += '</div>';

			$('#ggm-home-course').html(html);
		}

		// 3b. Courses View
		function renderCourses(d) {
			$('#ggm-dash').removeClass('ggm-course-mode');
			var html = '';

			// Sub-section: Enrolled
			html += '<div class="ggm-home-section">';
			html += '  <h3 class="ggm-section-title">My Enrolled Courses</h3>';
			if (d.enrolled_courses.length > 0) {
				html += '  <div class="ggm-cards-grid">';
				$.each(d.enrolled_courses, function(i, c) {
					html += courseCard(c);
				});
				html += '  </div>';
			} else {
				html += '  <div class="ggm-empty" style="padding: 20px;">';
				html += '    <p>You have no active courses currently enrolled.</p>';
				html += '  </div>';
			}
			html += '</div>';

			// Sub-section: Available to purchase
			html += '<div class="ggm-home-section" style="margin-top: 40px;">';
			html += '  <h3 class="ggm-section-title">Available Courses to Purchase</h3>';
			if (d.unenrolled_courses.length > 0) {
				html += '  <div class="ggm-cards-grid">';
				$.each(d.unenrolled_courses, function(i, c) {
					html += unenrolledCourseCard(c);
				});
				html += '  </div>';
			} else {
				html += '  <div class="ggm-empty" style="padding: 20px;">';
				html += '    <p>No other courses available currently.</p>';
				html += '  </div>';
			}
			html += '</div>';

			$('#ggm-courses-list').html(html);
		}

		function findCourse(courseId) {
			if (!dashboardData) return null;
			return (dashboardData.enrolled_courses || []).find(function(c) {
				return parseInt(c.id, 10) === parseInt(courseId, 10);
			}) || null;
		}

		function openCoursePlayer(courseId, lessonId) {
			var course = findCourse(courseId);
			if (!course) return;
			$('#ggm-dash').addClass('ggm-course-mode');
			var lessons = course.lessons || [];
			var activeIndex = 0;
			if (lessonId) {
				lessons.some(function(l, i) {
					if (parseInt(l.id, 10) === parseInt(lessonId, 10)) { activeIndex = i; return true; }
					return false;
				});
			} else {
				var firstIncomplete = lessons.findIndex(function(l) { return !l.completed; });
				activeIndex = firstIncomplete >= 0 ? firstIncomplete : 0;
			}
			var completed = lessons.filter(function(l) { return l.completed; }).length;
			var progress = lessons.length ? Math.round((completed / lessons.length) * 100) : 0;
			var active = lessons[activeIndex] || null;

			var html = '<div class="ggm-course-player">';
			html += '<aside class="ggm-course-outline">';
			html += '<button type="button" class="ggm-course-back">&larr; Back to courses</button>';
			html += '<div class="ggm-course-summary">';
			if (course.thumbnail) html += '<div class="ggm-course-summary-thumb" style="background-image:url(\'' + escapeHtml(course.thumbnail) + '\')"></div>';
			html += '<h2>' + escapeHtml(course.title) + '</h2>';
			html += '<div class="ggm-course-progress"><span style="width:' + progress + '%"></span></div>';
			html += '<p><strong>' + progress + '% complete</strong> &middot; ' + completed + ' of ' + lessons.length + ' lessons</p>';
			html += '</div>';
			html += '<button type="button" class="ggm-curriculum-toggle" aria-expanded="false"><span>Course lessons</span><strong>' + (activeIndex + 1) + ' / ' + lessons.length + '</strong><i aria-hidden="true">&#9662;</i></button>';
			html += '<nav class="ggm-course-lessons" aria-label="Course lessons">';
			lessons.forEach(function(l, i) {
				html += '<button type="button" class="ggm-course-lesson' + (i === activeIndex ? ' is-active' : '') + '" data-course="' + course.id + '" data-lesson="' + l.id + '">';
				html += '<span class="ggm-lesson-state">' + (i + 1) + '</span><div><p class="ggm-course-lesson-title">' + escapeHtml(l.title) + '</p>';
				if (l.duration) html += '<small>' + escapeHtml(l.duration) + '</small>';
				html += '</div>';
				if (l.completed) html += '<span class="ggm-lesson-complete" aria-label="Completed">&#10003;</span>';
				html += '</button>';
			});
			html += '</nav></aside>';

			html += '<main class="ggm-course-content">';
			if (!active) {
				html += '<div class="ggm-course-empty"><h2>Course content is coming soon</h2><p>No lessons have been published for this course yet.</p></div>';
			} else {
				html += '<div class="ggm-lesson-heading"><div><span>Lesson ' + (activeIndex + 1) + ' of ' + lessons.length + '</span><h3>' + escapeHtml(active.title) + '</h3></div><span class="ggm-status-pill">' + (active.completed ? 'Completed' : 'In progress') + '</span></div>';
				if (active.video) html += '<div class="ggm-course-video">' + active.video + '</div>';
				html += '<article class="ggm-lesson-body">' + (active.content || '<p>Lesson content will appear here.</p>') + '</article>';
				html += '<div class="ggm-lesson-actions">';
				html += '<button type="button" class="ggm-btn ggm-course-prev" data-course="' + course.id + '" data-index="' + (activeIndex - 1) + '"' + (activeIndex === 0 ? ' disabled' : '') + '>&larr; Previous</button>';
				html += '<button type="button" class="ggm-btn ggm-btn-primary ggm-toggle-complete" data-course="' + course.id + '" data-lesson="' + active.id + '">' + (active.completed ? 'Mark incomplete' : 'Mark complete') + '</button>';
				html += '<button type="button" class="ggm-btn ggm-course-next" data-course="' + course.id + '" data-index="' + (activeIndex + 1) + '"' + (activeIndex === lessons.length - 1 ? ' disabled' : '') + '>Next &rarr;</button>';
				html += '</div>';
			}
			html += '</main></div>';
			$('#ggm-courses-list').html(html);
			installMobileVideoTap();
			$('#tab-courses').scrollTop(0);
		}

		function installMobileVideoTap() {
			if (!window.matchMedia || !window.matchMedia('(max-width: 700px)').matches) return;
			$('.ggm-course-video iframe').each(function() {
				var $iframe = $(this), src = $iframe.attr('src') || '';
				if (!/youtube(?:-nocookie)?\.com\/embed\//i.test(src)) return;
				$iframe.attr('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
				if (!$iframe.siblings('.ggm-mobile-video-tap').length) {
					$iframe.after('<button type="button" class="ggm-mobile-video-tap" aria-label="Play video"></button>');
				}
			});
		}

		$(document).on('click touchend', '.ggm-mobile-video-tap', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var $tap = $(this), $iframe = $tap.siblings('iframe').first();
			if (!$iframe.length) { $tap.remove(); return; }
			$tap.css({ display: 'none', pointerEvents: 'none' });
			var src = $iframe.attr('src') || '';
			try {
				var url = new URL(src, window.location.href);
				url.searchParams.set('autoplay', '1');
				url.searchParams.set('playsinline', '1');
				$iframe.attr('src', url.toString());
			} catch (err) {
				$iframe.attr('src', src + (src.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1&playsinline=1');
			}
			setTimeout(function() { $tap.remove(); }, 50);
		});

		$(document).on('click', '.ggm-open-course', function(e) {
			e.preventDefault();
			switchTab('courses');
			openCoursePlayer($(this).data('course'));
		});
		$(document).on('click', '.ggm-course-back', function() { renderCourses(dashboardData); });
		$(document).on('click', '.ggm-curriculum-toggle', function() {
			var $toggle = $(this), isOpen = $toggle.attr('aria-expanded') === 'true';
			$toggle.attr('aria-expanded', isOpen ? 'false' : 'true');
			$toggle.next('.ggm-course-lessons').toggleClass('is-open', !isOpen);
		});
		$(document).on('click', '.ggm-course-lesson', function() { openCoursePlayer($(this).data('course'), $(this).data('lesson')); });
		$(document).on('click', '.ggm-course-prev, .ggm-course-next', function() {
			var course = findCourse($(this).data('course'));
			var lesson = course && course.lessons[$(this).data('index')];
			if (lesson) openCoursePlayer(course.id, lesson.id);
		});
		$(document).on('click', '.ggm-toggle-complete', function() {
			var $button = $(this), course = findCourse($button.data('course'));
			var lesson = course && (course.lessons || []).find(function(l) { return parseInt(l.id, 10) === parseInt($button.data('lesson'), 10); });
			if (!lesson) return;
			$button.prop('disabled', true);
			$.post(ggm_public.ajaxurl || ggm_public.ajax_url, { action:'ggm_complete_lesson', nonce:ggm_public.nonce, lesson_id:lesson.id, complete:lesson.completed ? 0 : 1 })
				.done(function(res) { if (res.success) { lesson.completed = !lesson.completed; openCoursePlayer(course.id, lesson.id); } })
				.always(function() { $button.prop('disabled', false); });
		});

		// 3c. Workshops View
		function renderWorkshops(d) {
			var html = '';
			var allWorkshops = (d.free_workshops || []).concat(d.paid_workshops || []);
			var freeWorkshops = allWorkshops.filter(function(w) {
				return (parseFloat(w.price) || 0) <= 0;
			});
			var premiumWorkshops = allWorkshops.filter(function(w) {
				return (parseFloat(w.price) || 0) > 0;
			});

			// Sub-section: Free Workshops. Hide the entire section when empty.
			if (freeWorkshops.length > 0) {
				html += '<div class="ggm-home-section">';
				html += '  <h3 class="ggm-section-title">Free Live Workshops</h3>';
				html += '  <div class="ggm-cards-grid ggm-workshop-cards-grid">';
				$.each(freeWorkshops, function(i, w) {
					html += workshopCard(w);
				});
				html += '  </div>';
				html += '</div>';
			}

			// Sub-section: Paid Workshops
			html += '<div class="ggm-home-section"' + (freeWorkshops.length ? ' style="margin-top: 40px;"' : '') + '>';
			html += '  <h3 class="ggm-section-title">Upcoming Premium Workshops</h3>';
			if (premiumWorkshops.length > 0) {
				html += '  <div class="ggm-cards-grid ggm-workshop-cards-grid">';
				$.each(premiumWorkshops, function(i, w) {
					html += workshopCard(w, { lockPriced: true });
				});
				html += '  </div>';
			} else {
				html += '  <div class="ggm-empty" style="padding: 20px;">';
				html += '    <p>No premium workshops scheduled at this time.</p>';
				html += '  </div>';
			}
			html += '</div>';

			$('#ggm-free-list').html(html);
		}

		// 3d. Profile View
		function renderProfile(p) {
			var html = '';
			html += '<div class="ggm-profile-card">';
			html += '  <div class="ggm-profile-header">';
			html += '    <img src="' + escapeHtml(p.avatar) + '" alt="Avatar" class="ggm-profile-avatar" />';
			html += '    <div>';
			html += '      <h3>' + escapeHtml(p.name) + '</h3>';
			html += '    </div>';
			html += '  </div>';

			html += '  <form id="ggm-profile-update-form" method="post">';
			html += '    <div class="ggm-pf-row">';
			html += '      <div class="ggm-pf-group">';
			html += '        <label>First Name</label>';
			html += '        <input type="text" id="ggm-pf-fname" class="ggm-profile-input" required value="' + escapeHtml(p.first_name) + '" />';
			html += '      </div>';
			html += '      <div class="ggm-pf-group">';
			html += '        <label>Last Name</label>';
			html += '        <input type="text" id="ggm-pf-lname" class="ggm-profile-input" value="' + escapeHtml(p.last_name) + '" />';
			html += '      </div>';
			html += '    </div>';

			html += '    <div class="ggm-pf-row ggm-pf-contact-row">';
			html += '      <div class="ggm-pf-group">';
			html += '        <label>Email Address</label>';
			html += '        <input type="email" id="ggm-pf-email" class="ggm-profile-input" required value="' + escapeHtml(p.email) + '" />';
			html += '      </div>';
			html += '      <div class="ggm-pf-group">';
			html += '        <label>WhatsApp Number</label>';
			var selectedCode = p.whatsapp_country_code || '+91';
			var selectedCountry = GGM_COUNTRY_CODES.find(function(c) { return c.code === selectedCode; }) || GGM_COUNTRY_CODES[0] || { code: '+91', iso: 'IN', name: 'India', flag: '' };
			html += '        <div class="ggm-profile-phone-control">';
			html += '          <div class="ggm-profile-country-picker">';
			html += '            <input type="hidden" id="ggm-pf-country-code" data-country-value value="' + escapeHtml(selectedCountry.code || '+91') + '">';
			html += '            <button type="button" class="ggm-profile-country-toggle" aria-haspopup="listbox" aria-expanded="false">';
			if (selectedCountry.flag) html += '<img src="' + escapeHtml(selectedCountry.flag) + '" alt="">';
			html += '<span>' + escapeHtml(selectedCountry.code || '+91') + '</span></button>';
			html += '            <div class="ggm-profile-country-menu" role="listbox">';
			html += '              <input type="search" class="ggm-profile-country-search" placeholder="Search country or code" aria-label="Search countries">';
			$.each(GGM_COUNTRY_CODES, function(i, c) {
				var countryName = c.name || c.iso || '';
				var search = (countryName + ' ' + (c.iso || '') + ' ' + c.code).toLowerCase();
				html += '<button type="button" class="ggm-profile-country-option" role="option" data-code="' + escapeHtml(c.code) + '" data-flag="' + escapeHtml(c.flag || '') + '" data-search="' + escapeHtml(search) + '" aria-selected="' + (c.code === selectedCountry.code ? 'true' : 'false') + '">';
				if (c.flag) html += '<img src="' + escapeHtml(c.flag) + '" alt="" loading="lazy">';
				html += '<span>' + escapeHtml(countryName ? countryName + ' ' + c.code : (c.label || c.code)) + '</span></button>';
			});
			html += '            </div></div>';
			html += '          <input type="tel" id="ggm-pf-phone" class="ggm-profile-input" inputmode="tel" autocomplete="tel-national" required value="' + escapeHtml(p.phone) + '" />';
			html += '        </div>';
			html += '      </div>';
			html += '    </div>';

			html += '    <div style="margin-top: 25px; display: flex; align-items: center; gap: 15px;">';
			html += '      <button type="submit" id="ggm-profile-submit-btn" class="ggm-btn ggm-btn-primary">Save Profile Changes</button>';
			html += '      <span class="spinner" id="ggm-profile-spinner"></span>';
			html += '    </div>';
			html += '    <div id="ggm-profile-feedback" class="ggm-feedback-msg" style="margin-top: 15px; display: none;"></div>';
			html += '  </form>';
			html += '</div>';

			$('#ggm-profile-wrap').html(html);
			initProfileCountryPicker();
		}

		function initProfileCountryPicker() {
			var picker = document.querySelector('.ggm-profile-wrap .ggm-profile-country-picker, #ggm-profile-wrap .ggm-profile-country-picker');
			if (!picker) return;
			var toggle = picker.querySelector('.ggm-profile-country-toggle');
			var hidden = picker.querySelector('[data-country-value]');
			var search = picker.querySelector('.ggm-profile-country-search');
			var options = Array.prototype.slice.call(picker.querySelectorAll('.ggm-profile-country-option'));
			function closePicker() { picker.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); }
			toggle.addEventListener('click', function() {
				var open = !picker.classList.contains('is-open');
				picker.classList.toggle('is-open', open);
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				if (open) { search.value = ''; options.forEach(function(option) { option.hidden = false; }); window.setTimeout(function() { search.focus(); }, 0); }
			});
			search.addEventListener('input', function() {
				var query = search.value.trim().toLowerCase();
				options.forEach(function(option) { option.hidden = !!query && (option.getAttribute('data-search') || '').indexOf(query) === -1; });
			});
			options.forEach(function(option) {
				option.addEventListener('click', function() {
					hidden.value = option.getAttribute('data-code') || hidden.value;
					var label = toggle.querySelector('span');
					var image = toggle.querySelector('img');
					var flag = option.getAttribute('data-flag') || '';
					label.textContent = hidden.value;
					if (flag) { if (!image) { image = document.createElement('img'); image.alt = ''; toggle.insertBefore(image, label); } image.src = flag; }
					options.forEach(function(item) { item.setAttribute('aria-selected', item === option ? 'true' : 'false'); });
					closePicker(); toggle.focus();
				});
			});
			document.addEventListener('click', function(event) { if (!picker.contains(event.target)) closePicker(); });
			picker.addEventListener('keydown', function(event) { if (event.key === 'Escape') { closePicker(); toggle.focus(); } });
		}

		// ─────────────────────────────────────────────────────────────────────
		// 4. HTML Cards Formatting Builders
		// ─────────────────────────────────────────────────────────────────────

		// Card: Enrolled Course
		function courseCard(c) {
			var symbol = ggm_public.currency_symbol || '₹';
			var startText = ggm_public.label_start_learning || 'Start Learning';

			var html = '';
			html += '<article class="ggm-card">';
			if (c.thumbnail) {
				html += '  <div class="ggm-card-thumb" style="background-image: url(\'' + escapeHtml(c.thumbnail) + '\');"></div>';
			}
			html += '  <div class="ggm-card-body">';
			html += '    <h3 class="ggm-card-title">' + escapeHtml(c.title) + '</h3>';
			html += '    <p class="ggm-card-desc">' + escapeHtml(c.short_desc) + '</p>';
			html += '    <div class="ggm-card-meta">';
			html += '      <span>📺 ' + c.video_count + ' ' + (ggm_public.label_videos || 'Videos') + '</span>';
			html += '    </div>';
			html += '    <button type="button" data-course="' + c.id + '" class="ggm-btn ggm-btn-primary wide ggm-open-course" style="margin-top: 15px;">' + escapeHtml(startText) + '</button>';
			html += '  </div>';
			html += '</article>';
			return html;
		}

		// Card: Available Course
		function unenrolledCourseCard(c) {
			var price = parseFloat(c.price) || 0;
			var priceDisplay = c.price_display || ((ggm_public.currency_symbol || '₹') + price);
			var hasCheckout = !! c.checkout_url;
			var unlockText = c.is_free
				? (ggm_public.label_watch_free || 'Enroll Free')
				: (hasCheckout ? (ggm_public.label_unlock || 'Unlock This Course') + ' ' + priceDisplay : 'Locked');

			var html = '';
			html += '<article class="ggm-card' + (! c.is_free ? ' ggm-card-locked' : '') + '">';
			if (c.thumbnail) {
				html += '  <div class="ggm-card-thumb" style="background-image: url(\'' + escapeHtml(c.thumbnail) + '\');">';
				if (! c.is_free) {
					html += '    <div class="ggm-lock-overlay">🔒</div>';
				}
				html += '  </div>';
			}
			html += '  <div class="ggm-card-body">';
			html += '    <h3 class="ggm-card-title">' + escapeHtml(c.title) + '</h3>';
			html += '    <p class="ggm-card-desc">' + escapeHtml(c.short_desc) + '</p>';
			html += '    <div class="ggm-card-meta">';
			html += '      <span>📺 ' + c.video_count + ' ' + (ggm_public.label_videos || 'Videos') + '</span>';
			html += '    </div>';
			if (hasCheckout) {
				html += '    <div class="ggm-card-price">' + escapeHtml(priceDisplay) + '</div>';
				html += '    <a href="' + escapeHtml(c.checkout_url) + '" class="ggm-btn ggm-btn-primary wide" style="margin-top: 15px;">' + escapeHtml(unlockText) + '</a>';
			} else {
				html += '    <button type="button" class="ggm-btn ggm-btn-primary wide ggm-btn-disabled" disabled aria-disabled="true" style="margin-top: 15px;">' + escapeHtml(unlockText) + '</button>';
			}
			html += '  </div>';
			html += '</article>';
			return html;
		}

		// Card: Workshop (Free/Paid)
		function workshopCard(w, options) {
			options = options || {};
			var price = parseFloat(w.price) || 0;
			var isPriced = price > 0;
			var priceDisplay = w.price_display || '';
			var buttonHtml = '';
			var isLocked = options.lockPriced ? (isPriced && ! w.has_access) : (! w.has_access && isPriced);

			var html = '';
			html += '<article class="ggm-card' + (isLocked ? ' ggm-card-locked' : '') + '" data-workshop-id="' + escapeHtml(w.id) + '">';
			if (w.thumbnail) {
				html += '  <div class="ggm-card-thumb" style="background-image: url(\'' + escapeHtml(w.thumbnail) + '\');">';
				if (isLocked) {
					html += '    <div class="ggm-lock-overlay">🔒</div>';
				}
				html += '  </div>';
			}
			html += '  <div class="ggm-card-body">';
			html += '    <h3 class="ggm-card-title">' + escapeHtml(w.title) + '</h3>';
			html += '    <div class="ggm-card-meta">';
			if (w.date) {
				html += '      <span>📅 Start: ' + escapeHtml(w.date) + '</span>';
			}
			if (w.end_date) {
				html += '      <span>🏁 End: ' + escapeHtml(w.end_date) + '</span>';
			}
			if (w.slots_list && w.slots_list.length) {
				// Only whatever slots are actually configured show up here —
				// one added shows one row, none added shows nothing.
				$.each(w.slots_list, function (i, s) {
					html += '      <span class="ggm-card-slot-row">🕐 ' + escapeHtml(s.type) + ': ' + escapeHtml(s.time) + '</span>';
				});
			} else if (w.duration) {
				html += '      <span>⏱ ' + escapeHtml(w.duration) + '</span>';
			}
			html += '      <div class="ggm-card-meta-wrap">';
			html += '        <span>🌐 ' + escapeHtml(w.mode) + '</span>';
			if (w.language) {
				html += '        <span>🗣 ' + escapeHtml(w.language) + '</span>';
			}
			html += '      </div>';
			html += '    </div>';
			if (priceDisplay) {
				html += '    <div class="ggm-card-price">' + priceDisplay + '</div>';
			}

			if (isLocked) {
				// Quick Buy — opens the real Razorpay popup right here on the
				// dashboard for this already-logged-in member; no more
				// redirect to a separate checkout page.
				buttonHtml = w.is_contribution
					? '<a class="ggm-btn ggm-btn-primary wide" href="' + escapeHtml(w.checkout_url) + '">' + escapeHtml(ggm_public.label_purchase_now || 'Contribute Now') + '</a>'
					: '<button type="button" class="ggm-btn ggm-btn-primary wide ggm-quick-buy-btn" data-workshop="' + escapeHtml(w.id) + '">' + escapeHtml(ggm_public.label_purchase_now || 'Purchase Now') + '</button>';
			} else if (w.has_access) {
				// Task 9 — server-computed countdown/unlock "Start Learning" button.
				buttonHtml = buildStartLearningButton(w);
			} else {
				buttonHtml = '<button type="button" class="ggm-btn ggm-btn-primary wide ggm-btn-disabled" disabled aria-disabled="true">' + escapeHtml(ggm_public.label_start_learning || 'Watch Now') + '</button>';
			}
			var whatsappHtml = w.has_access && w.whatsapp_group_url
				? '<a href="' + escapeHtml(w.whatsapp_group_url) + '" target="_blank" rel="noopener noreferrer" class="ggm-btn ggm-btn-outline wide ggm-workshop-whatsapp-btn"><svg class="ggm-workshop-whatsapp-btn__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.5 11.8a8.4 8.4 0 0 1-12.4 7.4L3.5 20.5l1.3-4.4A8.4 8.4 0 1 1 20.5 11.8Z"/><path d="M8.7 7.8c.2-.5.4-.5.7-.5h.5c.2 0 .4.1.5.4l.7 1.7c.1.3.1.5-.1.7l-.5.6c.7 1.3 1.7 2.3 3 3l.6-.5c.2-.2.4-.2.7-.1l1.7.7c.3.1.4.3.4.5v.5c0 .3 0 .5-.5.7-.5.2-1 .3-1.5.2-2.8-.6-5.6-3.4-6.2-6.2-.1-.5 0-1 .2-1.5Z"/></svg><span>Join WhatsApp Group</span></a>'
				: '';
			// "View Details" always sits above the primary action — a plain
			// labeled button reads clearer here than the old eye icon did.
			html += '    <div class="ggm-card-actions">';
			html += '      <a href="' + escapeHtml(w.permalink) + '" class="ggm-btn ggm-btn-outline wide">' + escapeHtml(ggm_public.label_view_details || 'View Details') + '</a>';
			html +=        whatsappHtml;
			html +=        buttonHtml;
			html += '    </div>';
			html += '  </div>';
			html += '</article>';
			return html;
		}

		// ─────────────────────────────────────────────────────────────────────
		// 4b. "Start Learning" countdown/unlock button (Task 9)
		//
		// The button's starting state, and the exact number of seconds
		// remaining, come from the server (GGM_Workshop_Slot::compute_status(),
		// using the site's configured timezone) on every dashboard load — so
		// refreshing the page always re-syncs to the true remaining time
		// instead of drifting from a stale client-side guess. The 1-second
		// ticker below only counts that server-seeded number down for a
		// smooth display; it never invents or re-derives the target time
		// from the browser's own clock.
		// ─────────────────────────────────────────────────────────────────────
		var ggmSlotTimers = {};
		var ggmSlotTickerStarted = false;
		var HOUR_IN_SECONDS_JS = 3600;

		function pad2(n) {
			n = Math.max(0, Math.floor(n));
			return n < 10 ? '0' + n : String(n);
		}

		function formatCountdown(totalSeconds) {
			totalSeconds = Math.max(0, Math.floor(totalSeconds));
			var hours   = Math.floor(totalSeconds / 3600);
			var minutes = Math.floor((totalSeconds % 3600) / 60);
			var seconds = totalSeconds % 60;
			if (hours > 0) {
				return pad2(hours) + ':' + pad2(minutes) + ':' + pad2(seconds);
			}
			return pad2(minutes) + ':' + pad2(seconds);
		}

		function buildStartLearningButton(w) {
			var s = w.slot_info;

			if (!w.has_slots) {
				// Purchased, but the admin hasn't configured any session yet.
				return '<button type="button" class="ggm-btn ggm-btn-primary wide ggm-btn-disabled" disabled aria-disabled="true">' + escapeHtml(ggm_public.label_start_learning || 'Start Learning') + '</button>';
			}

			if (!s) {
				// Every slot has already ended — nothing left to attend.
				return '<button type="button" class="ggm-btn ggm-btn-primary wide ggm-btn-disabled" disabled aria-disabled="true">' + escapeHtml(ggm_public.label_workshop_completed || 'Workshop Completed') + '</button>';
			}

			var uid = 'ggm-start-learning-' + w.id;

			if ('unlocked' === s.state) {
				return '<a id="' + uid + '" href="' + escapeHtml(s.meeting_link || '#') + '" target="_blank" rel="noopener" class="ggm-btn ggm-btn-primary wide">' + escapeHtml(ggm_public.label_start_learning || 'Start Learning') + '</a>';
			}

			if ('countdown' === s.state) {
				// Within 1 hour of start — real ticking countdown to unlock
				// (10 minutes before the slot's start time).
				ggmSlotTimers[w.id] = {
					phase:           'unlock',
					secondsToUnlock: s.seconds_to_unlock,
					meetingLink:     s.meeting_link
				};
				var countdownLabel = (ggm_public.label_starts_in || 'Starts in') + ' ' + formatCountdown(s.seconds_to_unlock);
				return '<button type="button" id="' + uid + '" class="ggm-btn ggm-btn-primary wide ggm-btn-disabled" disabled aria-disabled="true">' + escapeHtml(countdownLabel) + '</button>';
			}

			// 'future' — more than 1 hour before start: a plain "Start Soon"
			// label instead of a distracting multi-day ticking countdown.
			// Still seeded with a timer so it flips to the real countdown the
			// moment it crosses the 1-hour mark, with no page refresh needed.
			ggmSlotTimers[w.id] = {
				phase:              'future',
				secondsToCountdown: Math.max(0, s.seconds_to_start - HOUR_IN_SECONDS_JS),
				secondsToUnlock:    s.seconds_to_unlock,
				meetingLink:        s.meeting_link
			};
			return '<button type="button" id="' + uid + '" class="ggm-btn ggm-btn-primary wide ggm-btn-disabled" disabled aria-disabled="true">' + escapeHtml(ggm_public.label_start_soon || 'Start Soon') + '</button>';
		}

		function tickSlotTimers() {
			$.each(ggmSlotTimers, function(workshopId, timer) {
				var $btn = $('#ggm-start-learning-' + workshopId);
				if (!$btn.length) {
					delete ggmSlotTimers[workshopId];
					return;
				}

				if ('future' === timer.phase) {
					timer.secondsToCountdown = Math.max(0, timer.secondsToCountdown - 1);
					timer.secondsToUnlock    = Math.max(0, timer.secondsToUnlock - 1);
					if (timer.secondsToCountdown > 0) {
						return; // Still more than 1 hour away — leave "Start Soon" as-is.
					}
					// Just crossed the 1-hour mark — switch to the real countdown.
					timer.phase = 'unlock';
					$btn.text((ggm_public.label_starts_in || 'Starts in') + ' ' + formatCountdown(timer.secondsToUnlock));
					return;
				}

				// phase === 'unlock' — ticking down to the meeting link unlocking.
				timer.secondsToUnlock = Math.max(0, timer.secondsToUnlock - 1);
				if (timer.secondsToUnlock <= 0) {
					// Crossed the 10-minutes-before-start unlock threshold —
					// swap the disabled button for a live link in place.
					var $link = $(
						'<a id="ggm-start-learning-' + workshopId + '" href="' + escapeHtml(timer.meetingLink || '#') + '" target="_blank" rel="noopener" class="ggm-btn ggm-btn-primary wide">' +
						escapeHtml(ggm_public.label_start_learning || 'Start Learning') +
						'</a>'
					);
					$btn.replaceWith($link);
					delete ggmSlotTimers[workshopId];
					return;
				}
				$btn.text((ggm_public.label_starts_in || 'Starts in') + ' ' + formatCountdown(timer.secondsToUnlock));
			});
		}

		function ensureSlotTicker() {
			if (ggmSlotTickerStarted) {
				return;
			}
			ggmSlotTickerStarted = true;
			setInterval(tickSlotTimers, 1000);
		}
		ensureSlotTicker();

		// ─────────────────────────────────────────────────────────────────────
		// 4c. Quick Buy — "Purchase Now" opens the real Razorpay popup right
		// here on the dashboard instead of redirecting to the Checkout page.
		// The visitor is already logged in and already loaded on this page,
		// so their name/email/WhatsApp number come straight from the same
		// dashboard AJAX payload already in memory — no extra round trip.
		// ─────────────────────────────────────────────────────────────────────
		function ensureRazorpayLoaded() {
			if (typeof window.Razorpay === 'function') {
				return $.Deferred().resolve(window.Razorpay).promise();
			}

			if (razorpayLoadPromise) {
				return razorpayLoadPromise;
			}

			var deferred = $.Deferred();
			var script = document.createElement('script');
			var settled = false;
			var timeoutId;
			razorpayLoadPromise = deferred.promise();

			function finish(success) {
				if (settled) {
					return;
				}
				settled = true;
				window.clearTimeout(timeoutId);
				script.onload = null;
				script.onerror = null;

				if (success && typeof window.Razorpay === 'function') {
					deferred.resolve(window.Razorpay);
					return;
				}

				if (script.parentNode) {
					script.parentNode.removeChild(script);
				}
				razorpayLoadPromise = null;
				deferred.reject();
			}

			script.src = 'https://checkout.razorpay.com/v1/checkout.js';
			script.async = true;
			script.setAttribute('data-ggm-razorpay', '1');
			script.onload = function () {
				finish(true);
			};
			script.onerror = function () {
				finish(false);
			};
			timeoutId = window.setTimeout(function () {
				finish(false);
			}, 12000);
			(document.head || document.documentElement).appendChild(script);

			return razorpayLoadPromise;
		}

		function startQuickBuy($btn, resumeOptions) {
			resumeOptions = resumeOptions || {};
			var originalText = $btn.text();
			var workshopId   = resumeOptions.workshopId || $btn.data('workshop') || '';
			var courseId     = $btn.data('course') || '';
			var contributionOptionId = resumeOptions.contributionOptionId || '';
			if (workshopId && $btn.data('contribution') && !contributionOptionId) {
				window.location.href = $btn.attr('href');
				return;
			}
			if (quickBuyFlowActive) {
				return;
			}
			quickBuyFlowActive = true;
			var $quickBuyButtons = $('.ggm-quick-buy-btn');

			function resetButton() {
				quickBuyFlowActive = false;
				$quickBuyButtons.prop('disabled', false);
				$btn.text(originalText);
			}

			function fail(message) {
				window.alert(message || ggm_public.error || 'Something went wrong. Please try again.');
				resetButton();
			}

			$quickBuyButtons.prop('disabled', true);
			$btn.text(ggm_public.label_processing || 'Processing…');

			// Load the payment SDK before creating a paid order. This keeps an
			// unavailable CDN from both blocking dashboard startup and leaving a
			// pending server order that cannot be opened in the checkout modal.
			ensureRazorpayLoaded().done(function (RazorpayConstructor) {
				$.post(ggm_public.ajaxurl || ggm_public.ajax_url, {
					action:      'ggm_create_order',
					nonce:       ggm_public.nonce,
					workshop_id: workshopId,
					course_id:   courseId,
					contribution_option_id: contributionOptionId,
					ggm_currency: window.ggmSelectedCurrency || ggm_public.currency || ''
				}).done(function (res) {
					if (!res || !res.success) {
						fail(res && res.data && res.data.message);
						return;
					}
					if (res.data.free) {
						window.location.href = res.data.redirect;
						return;
					}
					openQuickBuyRazorpay(res.data, $btn, resetButton, fail, RazorpayConstructor);
				}).fail(function () {
					fail();
				});
			}).fail(function () {
				fail('Payment gateway failed to load. Please check your connection and try again.');
			});
		}

		$(document).on('click', '.ggm-quick-buy-btn', function (e) {
			e.preventDefault();
			startQuickBuy($(this));
		});

		function consumeEnrollmentResumeUrl() {
			if (!window.history || !window.history.replaceState) return;
			var clean = new URL(window.location.href);
			clean.searchParams.delete('ggm_enroll_workshop');
			clean.searchParams.delete('ggm_resume_payment');
			clean.searchParams.delete('ggm_contribution_option');
			window.history.replaceState({}, document.title, clean.pathname + clean.search + '#tab-free');
		}

		function resumeWorkshopEnrollment() {
			if (resumeHandled || !resumeWorkshopId || !dashboardData) return;
			resumeHandled = true;
			consumeEnrollmentResumeUrl();
			switchTab('free');

			var workshops = (dashboardData.free_workshops || []).concat(dashboardData.paid_workshops || []);
			var workshop = workshops.find(function(item) {
				return parseInt(item.id, 10) === resumeWorkshopId;
			});
			var $card = $('.ggm-card[data-workshop-id="' + resumeWorkshopId + '"]').first();
			if (!workshop || !$card.length) {
				window.alert('This workshop is no longer available.');
				return;
			}

			$card.addClass('ggm-workshop-resume-target');
			window.setTimeout(function() {
				$card[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
			}, 50);

			if (workshop.has_access || !resumePayment) {
				var accessAction = $card.find('.ggm-card-actions a, .ggm-card-actions button:not(:disabled)').last()[0];
				if (accessAction) window.setTimeout(function() { accessAction.focus({ preventScroll: true }); }, 500);
				return;
			}

			if (workshop.is_contribution && !resumeContributionOption) {
				var contributionLink = $card.find('.ggm-card-actions a[href*="workshop_id="]').last()[0];
				if (contributionLink) window.location.assign(contributionLink.href);
				return;
			}

			var $paymentButton = $card.find('.ggm-quick-buy-btn').first();
			if (!$paymentButton.length) {
				$paymentButton = $('<button type="button" class="ggm-btn ggm-btn-primary wide ggm-quick-buy-btn">Pay &amp; Enroll</button>');
				$card.find('.ggm-card-actions').append($paymentButton);
			}
			window.setTimeout(function() {
				startQuickBuy($paymentButton, {
					workshopId: resumeWorkshopId,
					contributionOptionId: resumeContributionOption
				});
			}, 650);
		}

		function openQuickBuyRazorpay(order, $btn, resetButton, fail, RazorpayConstructor) {
			if (typeof RazorpayConstructor !== 'function') {
				fail('Payment gateway failed to load. Please refresh the page and try again.');
				return;
			}

			var profile = (dashboardData && dashboardData.profile) || {};
			var contact = profile.phone
				? (profile.whatsapp_country_code || '') + profile.phone
				: '';

			var rzp;
			try {
				rzp = new RazorpayConstructor({
				key:         order.key_id,
				amount:      order.amount,
				currency:    order.currency,
				order_id:    order.order_id,
				name:        ggm_public.site_name || '',
				description: 'Workshop/Course Purchase',
				prefill: {
					name:    profile.name || '',
					email:   profile.email || '',
					contact: contact
				},
				theme: { color: ggm_public.theme_color || '#0e9e6e' },
				modal: {
					ondismiss: resetButton
				},
				handler: function (response) {
					$btn.text(ggm_public.label_verifying || 'Verifying…');
					$.post(ggm_public.ajaxurl || ggm_public.ajax_url, {
						action:               'ggm_verify_payment',
						nonce:                 ggm_public.nonce,
						razorpay_order_id:     response.razorpay_order_id,
						razorpay_payment_id:   response.razorpay_payment_id,
						razorpay_signature:    response.razorpay_signature,
						payment_id:            order.payment_id
					}).done(function (verifyRes) {
						if (verifyRes && verifyRes.success) {
							// Purchase is granted — back to the dashboard, which
							// now shows this workshop/course as purchased.
							window.location.href = verifyRes.data.redirect;
						} else {
							fail(verifyRes && verifyRes.data && verifyRes.data.message);
						}
					}).fail(function () {
						fail(ggm_public.error);
					});
				}
				});

				rzp.on('payment.failed', function () {
					fail(ggm_public.label_payment_failed);
				});

				rzp.open();
			} catch (paymentError) {
				fail('Payment gateway could not be opened. Please try again.');
			}
		}

		// ─────────────────────────────────────────────────────────────────────
		// 5. Submit Profile Update Form
		// ─────────────────────────────────────────────────────────────────────
		$(document).on('submit', '#ggm-profile-update-form', function(e) {
			e.preventDefault();

			var fname       = $('#ggm-pf-fname').val();
			var lname       = $('#ggm-pf-lname').val();
			var email       = $('#ggm-pf-email').val();
			var phone       = $('#ggm-pf-phone').val();
			var countryCode = $('#ggm-pf-country-code').val();

			var $submitBtn = $('#ggm-profile-submit-btn');
			var $spinner = $('#ggm-profile-spinner');
			var $feedback = $('#ggm-profile-feedback');

			$feedback.hide().removeClass('success error');
			$submitBtn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: ggm_public.ajax_url,
				type: 'POST',
				data: {
					action: 'ggm_update_profile',
					nonce: ggm_public.nonce,
					first_name: fname,
					last_name: lname,
					email: email,
					phone: phone,
					country_code: countryCode
				},
				dataType: 'json',
				success: function(response) {
					$submitBtn.prop('disabled', false);
					$spinner.removeClass('is-active');

					if (response.success) {
						$feedback.addClass('success').html(response.data.message || 'Changes saved successfully.').fadeIn();
					} else {
						$feedback.addClass('error').html(response.data.message || 'Error saving changes.').fadeIn();
					}
				},
				error: function(xhr) {
					$submitBtn.prop('disabled', false);
					$spinner.removeClass('is-active');
					var msg = 'Failed to save changes.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					$feedback.addClass('error').html(msg).fadeIn();
				}
			});
		});

		// 6. Utility Functions
		// ─────────────────────────────────────────────────────────────────────
		function escapeHtml(str) {
			if (str === null || typeof str === 'undefined') return '';
			str = String(str);
			return str
				.replace(/&/g, "&amp;")
				.replace(/</g, "&lt;")
				.replace(/>/g, "&gt;")
				.replace(/"/g, "&quot;")
				.replace(/'/g, "&#039;");
		}

		// ─────────────────────────────────────────────────────────────────────
		// 6. Static Profile Form (tab-profile.php rendered form)
		// ─────────────────────────────────────────────────────────────────────
		$(document).on('submit', '#ggm-profile-form', function(e) {
			e.preventDefault();
			var $btn = $(this).find('button[type=submit]');
			var $fb  = $('#ggm-profile-feedback');
			$btn.prop('disabled', true);
			$fb.hide();

			$.post(ggm_public.ajaxurl || ggm_public.ajax_url, {
				action:       'ggm_update_profile',
				nonce:        ggm_public.nonce || ggm_public.ajax_nonce,
				first_name:   $(this).find('#ggm_first_name').val(),
				last_name:    $(this).find('#ggm_last_name').val(),
				phone:        $(this).find('#ggm_phone').val(),
				country_code: $(this).find('#ggm_country_code').val(),
			}).done(function(res) {
				if (res.success) {
					$fb.text(res.data.message).css('color','#0e9e6e').show();
				} else {
					$fb.text(res.data.message).css('color','#e53e3e').show();
				}
			}).always(function() {
				$btn.prop('disabled', false);
			});
		});

		$(document).on('submit', '#ggm-change-password-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn  = $form.find('button[type=submit]');
			var $fb   = $('#ggm-change-password-feedback');
			var newPass     = $form.find('#ggm_new_password').val();
			var confirmPass = $form.find('#ggm_confirm_password').val();

			$fb.hide();

			if (newPass !== confirmPass) {
				$fb.text('New passwords do not match.').css('color','#e53e3e').show();
				return;
			}

			$btn.prop('disabled', true);

			$.post(ggm_public.ajaxurl || ggm_public.ajax_url, {
				action:           'ggm_change_password',
				nonce:            ggm_public.nonce || ggm_public.ajax_nonce,
				new_password:     newPass,
				confirm_password: confirmPass,
			}).done(function(res) {
				if (res.success) {
					$fb.text(res.data.message).css('color','#0e9e6e').show();
					$form[0].reset();
				} else {
					$fb.text(res.data.message).css('color','#e53e3e').show();
				}
			}).fail(function() {
				$fb.text('Something went wrong. Please try again.').css('color','#e53e3e').show();
			}).always(function() {
				$btn.prop('disabled', false);
			});
		});

		// ─────────────────────────────────────────────────────────────────────
		// 7. Avatar Upload
		// ─────────────────────────────────────────────────────────────────────
		var $fileInput = $('#ggm-avatar-file-input');
		var $feedback  = $('#ggm-avatar-upload-feedback');

		function showAvatarToast(msg, isError) {
			$feedback.text(msg)
				.css('background', isError ? '#e53e3e' : '#0e9e6e')
				.fadeIn(200);
			setTimeout(function() { $feedback.fadeOut(400); }, 3000);
		}

		// Click on any .ggm-avatar-trigger opens the file picker.
		$(document).on('click', '.ggm-avatar-trigger', function() {
			$fileInput.trigger('click');
		});

		$fileInput.on('change', function() {
			var file = this.files[0];
			if (!file) return;

			var allowed = ['image/jpeg','image/png','image/gif','image/webp'];
			if (allowed.indexOf(file.type) === -1) {
				showAvatarToast('Only JPG, PNG, GIF, WebP allowed.', true);
				return;
			}
			if (file.size > 2 * 1024 * 1024) {
				showAvatarToast('Image must be under 2 MB.', true);
				return;
			}

			var formData = new FormData();
			formData.append('action', 'ggm_upload_avatar');
			formData.append('nonce',  ggm_public.nonce || ggm_public.ajax_nonce);
			formData.append('avatar', file);

			showAvatarToast('Uploading…', false);

			$.ajax({
				url:         ggm_public.ajaxurl || ggm_public.ajax_url,
				type:        'POST',
				data:        formData,
				processData: false,
				contentType: false,
			}).done(function(res) {
				if (res.success) {
					var url = res.data.url;
					// Replace all avatar elements.
					$('.ggm-avatar-trigger').each(function() {
						var $trigger = $(this);
						$trigger.find('.ggm-avatar-letter').hide();
						var $img = $trigger.find('.ggm-avatar-img');
						if ($img.length) {
							$img.attr('src', url).show();
						} else {
							$trigger.prepend('<img src="' + url + '" class="ggm-avatar-img" alt="Avatar" style="width:100%; height:100%; object-fit:cover; border-radius:inherit;">');
						}
					});
					$('.ggm-profile-avatar img').attr('src', url);
					showAvatarToast(res.data.message || 'Avatar updated!', false);
				} else {
					showAvatarToast(res.data.message || 'Upload failed.', true);
				}
			}).fail(function() {
				showAvatarToast('Upload failed. Please try again.', true);
			});

			// Reset so the same file can be re-selected.
			$fileInput.val('');
		});

		function initDiseaseDashboardCards() {
			$('.ggm-disease-dashboard-card').each(function(index) {
				var card = this;
				var body = card.querySelector('.ggm-disease-dashboard-body');
				var button = card.querySelector('.ggm-disease-dashboard-toggle');
				if (!body || !button || $(card).hasClass('is-expanded')) {
					return;
				}

				if (!body.id) {
					body.id = 'ggm-disease-card-body-runtime-' + index;
				}

				card.classList.add('has-more');
				card.style.alignSelf = 'start';
				card.style.display = 'flex';
				card.style.flexDirection = 'column';
				card.style.height = '450px';
				card.style.maxHeight = '450px';
				card.style.overflow = 'hidden';
				body.style.flex = '1 1 auto';
				body.style.minHeight = '0';
				body.style.overflow = 'hidden';
				button.hidden = false;
				button.setAttribute('aria-expanded', 'false');
				button.setAttribute('aria-controls', body.id);
			});
		}

		// Launch.
		initTabs();
		initDashboardFormPopup();
		loadData();
	});

})(jQuery);
