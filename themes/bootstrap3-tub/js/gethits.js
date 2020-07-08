$(document).ready(function() {
    function formatNumber(number) {
        number = number.toString();
        var len = number.length;
        var thousands = number.substring(len-3,len);
        var millions = number.substring(len-6,len-3);
        var billions = number.substring(len-9,len-6);
        var formattedNumber = '';
        if (billions != '') {
            formattedNumber += billions + '.';
        }
        if (millions != '') {
            formattedNumber += millions + '.';
        }
        if (thousands != '') {
            formattedNumber += thousands;
        }
        return formattedNumber;
    }

    /*
     * Get result count for inactive Tabs
     */
    $('.searchTabResultCount').each(function(){
        var $this = $(this);
        var searchClass = $(this).data('searchclass');
        var queryString = $(this).data('query');
        var lookfor = $(this).data('lookfor');
        jQuery.ajax({
            url:'/AJAX/JSON?method=getResultCount',
            dataType:'json',
            data:{lookfor:lookfor, querystring:queryString, source:searchClass},
            success:function(data, textStatus){
                if (lookfor != "") {
                    if (searchClass == 'Primo') { var id='hitsprimo'; } else { var id='hitsgbv'; }
                    $this.find('a').append('<span class="matches" id="'+id+'"> ('+formatNumber(data.data.total)+')</span>');
                }
            }
        });
    });
});
