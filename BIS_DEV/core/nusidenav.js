/**
 * Nuvis-style Customizable User Side Navigation Drawer for nuBuilder
 * Access level navigation menu assigned by developer via Home Form and Access Forms.
 */

window.nuSideNav = {
    isOpen: false,
    items: [],

    init: function() {
        if ($('#nu-sidenav-drawer').length === 0) {
            this.injectHTML();
        }
        this.checkVisibility();
        this.loadNavItems();
    },

    checkVisibility: function() {
        // Detect login screen components in nuBuilder
        var isLoginPage = $('#nuLoginForm').length > 0 ||
                          $('#nuusername').length > 0 ||
                          $('#nupassword').length > 0 ||
                          $('.nuLogin').length > 0 ||
                          $('#login').length > 0;

        var hasActiveSession = window.nuSESSION && window.nuSESSION !== '';
        var hasLoadedForms = $('#nuRECORD').length > 0 || $('#nuBreadcrumbHolder').length > 0 || $('#nuTabHolder').length > 0;

        var isLoggedIn = (hasActiveSession || hasLoadedForms) && !isLoginPage;

        if (isLoggedIn) {
            $('#nu-sidenav-toggle-btn').css('display', 'inline-flex');
        } else {
            $('#nu-sidenav-toggle-btn').css('display', 'none');
            this.close();
        }
    },

    injectHTML: function() {
        var html = [
            '<style>',
            '#nu-sidenav-toggle-btn { position: fixed; top: 12px; left: 12px; z-index: 10000; background: #1e293b; color: #38bdf8; border: 1px solid #334155; border-radius: 6px; padding: 8px 14px; cursor: pointer; font-size: 14px; font-weight: 700; font-family: "Segoe UI", sans-serif; box-shadow: 0 4px 8px rgba(0,0,0,0.4); transition: all 0.2s; display: none; align-items: center; gap: 8px; }',
            '#nu-sidenav-toggle-btn:hover { background: #0f172a; border-color: #38bdf8; transform: scale(1.05); }',
            '#nu-sidenav-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 9998; display: none; }',
            '#nu-sidenav-drawer { position: fixed; top: 0; left: -280px; width: 280px; bottom: 0; background: #0f172a; border-right: 1px solid #334155; z-index: 9999; transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; justify-content: space-between; padding: 20px; box-sizing: border-box; color: #f8fafc; font-family: "Segoe UI", sans-serif; box-shadow: 8px 0 24px rgba(0,0,0,0.5); }',
            '#nu-sidenav-drawer.open { left: 0; }',
            '.nu-nav-header { display: flex; align-items: center; justify-content: space-between; padding-bottom: 16px; border-bottom: 1px solid #334155; margin-bottom: 20px; }',
            '.nu-nav-badge { display: flex; align-items: center; gap: 12px; }',
            '.nu-nav-avatar { width: 38px; height: 38px; border-radius: 50%; background: #0284c7; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; color: white; border: 2px solid #38bdf8; }',
            '.nu-nav-clinic { font-size: 13px; font-weight: 700; color: #f8fafc; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 160px; }',
            '.nu-nav-role { font-size: 10px; color: #38bdf8; font-family: monospace; text-transform: uppercase; font-weight: 700; }',
            '.nu-nav-close { cursor: pointer; color: #94a3b8; font-size: 18px; transition: color 0.2s; }',
            '.nu-nav-close:hover { color: #f87171; }',
            '.nu-nav-menu { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; flex: 1; overflow-y: auto; }',
            '.nu-nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 8px; color: #cbd5e1; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s; cursor: pointer; border: 1px solid transparent; }',
            '.nu-nav-item:hover { background: #1e293b; color: #38bdf8; border-color: #334155; }',
            '.nu-nav-item.home-item { background: #0284c7; color: white; border-color: #38bdf8; }',
            '.nu-nav-item.home-item:hover { background: #0369a1; }',
            '.nu-nav-item i { font-size: 16px; width: 20px; text-align: center; color: #38bdf8; }',
            '.nu-nav-item.home-item i { color: white; }',
            '.nu-nav-footer { border-top: 1px solid #334155; padding-top: 16px; margin-top: 16px; display: flex; flex-direction: column; gap: 8px; }',
            '.nu-nav-action-btn { background: #1e293b; border: 1px solid #334155; color: #f8fafc; padding: 10px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }',
            '.nu-nav-action-btn:hover { background: #0284c7; border-color: #38bdf8; }',
            '</style>',
            '<button id="nu-sidenav-toggle-btn" onclick="nuSideNav.toggle()"><i class="fa fa-bars"></i> Menu</button>',
            '<div id="nu-sidenav-overlay" onclick="nuSideNav.close()"></div>',
            '<div id="nu-sidenav-drawer">',
            '  <div>',
            '    <div class="nu-nav-header">',
            '      <div class="nu-nav-badge">',
            '        <div class="nu-nav-avatar">N</div>',
            '        <div>',
            '          <div class="nu-nav-clinic">Nuvis BIS Enterprise</div>',
            '          <div class="nu-nav-role" id="nu-nav-user-role">ACCESS CONTROLLED</div>',
            '        </div>',
            '      </div>',
            '      <i class="fa fa-times nu-nav-close" onclick="nuSideNav.close()"></i>',
            '    </div>',
            '    <ul class="nu-nav-menu" id="nu-nav-items-container">',
            '      <li style="color:#64748b; font-size:12px; padding:10px;">Loading navigation...</li>',
            '    </ul>',
            '  </div>',
            '  <div class="nu-nav-footer">',
            '    <button class="nu-nav-action-btn" onclick="nuOpenDashboard(); nuSideNav.close();"><i class="fa fa-tachometer"></i> Operations Center</button>',
            '    <button class="nu-nav-action-btn" style="background:#8f1d1d; border-color:#f87171;" onclick="nuLogout();"><i class="fa fa-sign-out"></i> Sign Out</button>',
            '  </div>',
            '</div>'
        ].join('\n');

        $('body').append(html);
    },

    toggle: function() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    },

    open: function() {
        this.isOpen = true;
        $('#nu-sidenav-drawer').addClass('open');
        $('#nu-sidenav-overlay').fadeIn(200);
    },

    close: function() {
        this.isOpen = false;
        $('#nu-sidenav-drawer').removeClass('open');
        $('#nu-sidenav-overlay').fadeOut(200);
    },

    loadNavItems: function() {
        var self = this;
        $.ajax({
            url: 'actions/dashboard_actions.php?action=get_nav_items',
            method: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' && res.items) {
                    self.items = res.items;
                    if (res.role) {
                        $('#nu-nav-user-role').text(res.role);
                    }
                    self.renderNav();
                }
            }
        });
    },

    renderNav: function() {
        var container = $('#nu-nav-items-container');
        container.empty();

        if (!this.items || this.items.length === 0) {
            container.append('<li style="color:#64748b; font-size:12px; padding:10px;">No accessible forms assigned</li>');
            return;
        }

        this.items.forEach(function(item) {
            var isHome = item.is_home ? 'home-item' : '';
            var actionStr = item.form_id ? "nuForm('" + item.form_id + "')" : (item.action || '');
            var li = [
                '<li class="nu-nav-item ' + isHome + '" onclick="' + actionStr + '; nuSideNav.close();">',
                '  <i class="fa ' + (item.icon || 'fa-file-text-o') + '"></i>',
                '  <span>' + item.label + (item.is_home ? ' (Home)' : '') + '</span>',
                '</li>'
            ].join('\n');
            container.append(li);
        });
    }
};

$(document).ready(function() {
    window.nuSideNav.init();

    // Check visibility on interval and AJAX complete
    setInterval(function() {
        window.nuSideNav.checkVisibility();
    }, 300);

    $(document).ajaxComplete(function() {
        window.nuSideNav.checkVisibility();
    });
});
