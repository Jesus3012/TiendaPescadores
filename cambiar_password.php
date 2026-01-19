<?php
include 'includes/session.php';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="content-wrapper">
    <section class="content pt-4">
        <div class="container-fluid">

            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-9 col-md-10 col-sm-12">

                    <div class="card shadow-lg border-0">
                        
                        <div class="card-header bg-warning text-dark">
                            <h3 class="card-title">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Seguridad de la cuenta
                            </h3>
                        </div>

                        <form id="formPassword" method="POST">
                            <div class="card-body">

                                <p class="text-muted mb-4">
                                    Por tu seguridad, utiliza una contraseña fuerte y evita reutilizar contraseñas anteriores.
                                </p>

                               <div class="form-group">
                                    <label>Contraseña actual</label>
                                    <div class="input-group">
                                        <input type="password" name="actual" id="actual" class="form-control" required>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary toggle-pass" data-target="actual">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Nueva contraseña</label>
                                    <div class="input-group">
                                        <input type="password" name="nueva" id="nueva" class="form-control" required>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary toggle-pass" data-target="nueva">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- MEDIDOR -->
                                    <div class="progress mt-2" style="height:6px;">
                                        <div id="strengthBar" class="progress-bar"></div>
                                    </div>
                                    <small id="strengthText" class="form-text text-muted"></small>
                                </div>

                                <div class="form-group">
                                    <label>Confirmar nueva contraseña</label>
                                    <div class="input-group">
                                        <input type="password" name="confirmar" id="confirmar" class="form-control" required>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary toggle-pass" data-target="confirmar">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-light border-left border-warning">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Se cerrará tu sesión después de actualizar la contraseña.
                                    </small>
                                </div>

                            </div>

                            <div class="card-footer bg-white text-right">
                                <button type="submit" class="btn btn-warning px-4">
                                    <i class="fas fa-save mr-1"></i>
                                    Actualizar contraseña
                                </button>
                            </div>
                        </form>

                    </div>

                </div>
            </div>

        </div>
    </section>
</div>

<script>
document.getElementById('formPassword').addEventListener('submit', function(e){
    e.preventDefault();

    const form = new FormData(this);

    fetch('procesar_cambio_password.php', {
        method: 'POST',
        body: form
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success'){
            Swal.fire({
                icon: 'success',
                title: 'Contraseña actualizada',
                text: 'Por seguridad deberás iniciar sesión nuevamente.',
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#f0ad4e'
            }).then(() => {
                window.location.href = 'login.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.msg,
                confirmButtonColor: '#d33'
            });
        }
    });
});

const nueva = document.getElementById('nueva');
const bar = document.getElementById('strengthBar');
const text = document.getElementById('strengthText');

nueva.addEventListener('input', () => {
    const val = nueva.value;
    let score = 0;

    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    bar.className = 'progress-bar';

    if (score <= 1) {
        bar.style.width = '25%';
        bar.classList.add('bg-danger');
        text.textContent = 'Contraseña débil';
    } else if (score === 2) {
        bar.style.width = '50%';
        bar.classList.add('bg-warning');
        text.textContent = 'Contraseña media';
    } else if (score === 3) {
        bar.style.width = '75%';
        bar.classList.add('bg-info');
        text.textContent = 'Contraseña buena';
    } else {
        bar.style.width = '100%';
        bar.classList.add('bg-success');
        text.textContent = 'Contraseña fuerte';
    }
});

document.querySelectorAll('.toggle-pass').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        const icon = btn.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
});

document.getElementById('formPassword').addEventListener('submit', function(e){
    if (document.getElementById('strengthBar').classList.contains('bg-danger')) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Contraseña insegura',
            text: 'Usa una contraseña más fuerte para continuar.',
            confirmButtonColor: '#f0ad4e'
        });
    }
});

</script>

