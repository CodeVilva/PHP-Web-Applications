<?php
$flash = get_flash();
?>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.js"></script>

  <?php if ($flash): ?>
  <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="flashToast" class="toast align-items-center text-bg-<?= e($flash['type']) ?> border-0 show" role="alert">
      <div class="d-flex">
        <div class="toast-body fw-medium">
          <i class="bi bi-info-circle-fill me-2"></i> <?= e($flash['message']) ?>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>
  <script>
    setTimeout(function() {
      const toastEl = document.getElementById('flashToast');
      if (toastEl) {
        const toast = new bootstrap.Toast(toastEl);
        toast.hide();
      }
    }, 4000);
  </script>
  <?php endif; ?>
</body>
</html>
