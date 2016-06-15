define('gapper/auth/gateway/select-organization', ['jquery'], function($) {
    var doSelect = function($container, data, value) {
        data = data || {};
        data = data.data;
        $container.html('<option>--</option>');
        for (var i = 0, l = data.length; i < l; i++) {
            var $tmpEle = $('<option></option>');
            $tmpEle.attr('value', data[i].code);
            $tmpEle.text(data[i].name);
            if (data[i].code === value) {
                $tmpEle.attr('selected', 'selected');
            }
            $container.append($tmpEle);
        }
    };

    var initDeps = function(pid, vid) {
        var $depHandler = $('select[name=department]');
        $depHandler.html('<option>--</option>');
        if (pid) $.get(['ajax/gapper/auth/gateway/organization/get-departments/', pid].join(''), function(result) {
            doSelect($depHandler, result, vid);
        });
    };

    var initSchools = function(force) {
        var $colHandler = $('select[name=school]');
        if ($colHandler.attr('data-inited') && ! force) return;
        $colHandler.attr('data-inited', true);
        var currentCol = $colHandler.attr('data-value');
        var $depHandler = $('select[name=department]');
        var currentDep = $depHandler.attr('data-value');
        $.get('ajax/gapper/auth/gateway/organization/get-schools', function(result) {
            doSelect($colHandler, result, currentCol);
            initDeps(currentCol, currentDep);
        });
    };

    $('body').on('change', 'select[name=school]', function() {
        $(this).attr('data-value', $(this).val());
        initSchools(true);
    });

    var run = function() {
        initSchools();
    };

    var response = {};
    response.loopMe = run;
    return response;
});

