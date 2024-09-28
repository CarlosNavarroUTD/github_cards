jQuery(document).ready(function($) {
    function fetchRepoInfo(username, repo, cardElement) {
        $.ajax({
            url: github_card_data.ajax_url,
            type: 'POST',
            data: {
                action: 'github_cards_fetch_repo_info',
                nonce: github_card_data.nonce,
                username: username,
                repo: repo
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    cardElement.find('.repo-name').text(data.name);
                    cardElement.find('.repo-description').text(data.description);
                    cardElement.find('.repo-updated').text('Última actualización: ' + new Date(data.updated_at).toLocaleDateString());
                    cardElement.find('.view-on-github').attr('href', data.html_url);

                    var languagesDiv = cardElement.find('.repo-languages');
                    languagesDiv.empty();
                    $.each(data.language, function(lang, value) {
                        $('<span>').addClass('language').text(lang).appendTo(languagesDiv);
                    });
                } else {
                    cardElement.find('.repo-name').text('Error al cargar la información');
                }
            },
            error: function() {
                cardElement.find('.repo-name').text('Error al cargar la información');
            }
        });
    }

    $('.github-card').each(function() {
        var username = $(this).data('username');
        var repo = $(this).data('repo');
        fetchRepoInfo(username, repo, $(this));
    });

    var swiper = new Swiper('.github-cards-carousel', {
        slidesPerView: 1,
        spaceBetween: 30,
        loop: true,
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
    });
});