define('gapper/auth/gateway/select-organization', ['jquery'], function($) {
    var doSelect = function($container, data, value) {
        data = data || {};
        data = data.data;
        $container.html('<option value="">--</option>');
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
        $depHandler.html('<option value="">--</option>');
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

    var initRooms = function(pid, vid) {
        var $currentRoomC = $('.control-room-container');
        if (!$currentRoomC.length) return;
        $currentRoomC.html('--');
        if (pid) $.get(['ajax/gapper/auth/gateway/location/get-rooms/', pid].join(''), function(result) {
            result = result || {};
            var data = result.data;
            if ($.isArray(data) && data.length) {
                var $control = $('<select name="room" class="form-control"></select>');
                doSelect($control, result, vid);
            } else {
                var $control = '<input type="text" name="room" class="form-control" />';
                $control.attr('value', vid);
            }
            $currentRoomC.empty().append($control);
        });
    };

    var initBuildings = function(pid, vid) {
        var $buildingHandler = $('select[name=building]');
        $buildingHandler.html('<option value="">--</option>');
        var $currentRoomC = $('.control-room-container');
        if ($currentRoomC.length) var currentRoom = $currentRoomC.attr('data-value');
        if (pid) $.get(['ajax/gapper/auth/gateway/location/get-buildings/', pid].join(''), function(result) {
            doSelect($buildingHandler, result, vid);
            initRooms(vid, currentRoom);
        });
    };

    var initCampuses = function(force) {
        var $campusHandler = $('select[name=campus]');
        if ($campusHandler.attr('data-inited') && ! force) return;
        $campusHandler.attr('data-inited', true);
        var currentCampus = $campusHandler.attr('data-value');
        var $buildingHandler = $('select[name=building]');
        var currentBuilding = $buildingHandler.attr('data-value');
        $.get('ajax/gapper/auth/gateway/location/get-campuses', function(result) {
            doSelect($campusHandler, result, currentCampus);
            initBuildings(currentCampus, currentBuilding);
        });
    };

    $('body').on('change', 'select[name=school]', function() {
        $(this).attr('data-value', $(this).val());
        initSchools(true);
    });

    $('body').on('change', 'select[name=campus]', function() {
        $(this).attr('data-value', $(this).val());
        initCampuses(true);
    });

    $('body').on('change', 'select[name=building]', function() {
        $(this).attr('data-value', $(this).val());
        var $currentRoomC = $('.control-room-container');
        if ($currentRoomC.length) var currentRoom = $currentRoomC.attr('data-value');
        initRooms($(this).val(), currentRoom);
    });

    var run = function() {
        initSchools();
        initCampuses();
    };

    var response = {};
    response.loopMe = run;
    return response;
});

