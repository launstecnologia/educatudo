<script>
document.getElementById('layout-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var formData = new FormData(this);
    fetch('<?= URL ?>/admin/dev/layout/save', {
        method: 'POST',
        body: formData
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (data && data.success) {
            showNotification(data.message, 'success');
        } else {
            showNotification('Erro: ' + ((data && data.error) ? data.error : 'não foi possível salvar'), 'error');
        }
    })
    .catch(function () {
        showNotification('Erro de conexão', 'error');
    });
});
</script>
