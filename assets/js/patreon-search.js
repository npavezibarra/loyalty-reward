jQuery(document).ready(function ($) {
    $('#patreon-search-input').autocomplete({
        source: function (request, response) {
            $.ajax({
                url: patreonSearch.ajax_url,
                method: 'POST',
                data: {
                    action: 'autocomplete_emails',
                    term: request.term,
                },
                success: function (data) {
                    response(data);
                },
                error: function () {
                    console.error('Error fetching autocomplete results.');
                },
            });
        },
        select: function (event, ui) {
            const selectedEmail = ui.item.value;
            // Fetch user details on selection
            $.ajax({
                url: patreonSearch.ajax_url,
                method: 'POST',
                data: {
                    action: 'get_user_details',
                    email: selectedEmail,
                },
                success: function (data) {
                    if (data.error) {
                        $('#patreon-user-details').html('<p>' + data.error + '</p>');
                    } else {
                        // Create a table with the user details
                        const userTable = `
                            <table>
                                <tr><th>Email</th><td>${data.email}</td></tr>
                                <tr><th>Label ID</th><td>${data.label}</td></tr>
                                <tr><th>Platform</th><td>${data.platform}</td></tr>
                                <tr><th>Date Added</th><td>${data.created_at}</td></tr>
                            </table>
                        `;
                        $('#patreon-user-details').html(userTable);
                    }
                },
                error: function () {
                    console.error('Error fetching user details.');
                },
            });
        },
    });
});
