{{-- resources/views/monthly_timesheets/approval/_approve_modal.blade.php --}}
<div class="modal fade" id="approveTimesheetModal" tabindex="-1" aria-labelledby="approveTimesheetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="approveTimesheetModalLabel">Konfirmasi Persetujuan Timesheet</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        {{-- Form di dalam modal --}}
        <form id="approveTimesheetForm" method="POST" action=""> {{-- Action diisi oleh JS --}}
          @csrf
          @method('PUT') {{-- Sesuaikan method route --}}
          <div class="modal-body">
            <p>Anda akan menyetujui timesheet untuk karyawan: <strong id="approveTimesheetUserName">Nama Karyawan</strong>.</p>
            {{-- Pesan tambahan akan diatur oleh JS --}}
            <p id="approveTimesheetAsistenNote" style="display: none;">Pengajuan akan diteruskan ke Manager untuk persetujuan final.</p>
            <p id="approveTimesheetManagerNote" style="display: none;">Ini adalah persetujuan final. Pastikan data sudah benar.</p>
            <p>Apakah Anda yakin ingin melanjutkan?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success">Ya, Setujui</button>
          </div>
        </form>
      </div>
    </div>
  </div>
