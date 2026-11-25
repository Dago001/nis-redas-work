<div class="redas-card" style="margin-bottom:14px;">
    <div class="card-head">
        <div class="card-head-title">
            <div class="card-head-icon" style="background:#eff6ff;color:#1d4ed8;">
                <i class="fas fa-list-check"></i>
            </div>
            Revenue Performance Report
        </div>
    </div>
    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;">Passport Revenue (Local)</label><input type="number" name="local_revenue_passport" class="ni fin-local-rev" min="0" step="any" placeholder="0" value="{{ old('local_revenue_passport') }}"></div>
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;">Visa Revenue (Local)</label><input type="number" name="local_revenue_visa" class="ni fin-local-rev" min="0" step="any" placeholder="0" value="{{ old('local_revenue_visa') }}"></div>
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;">E-Pass Revenue (Local)</label><input type="number" name="local_revenue_e_pass" class="ni fin-local-rev" min="0" step="any" placeholder="0" value="{{ old('local_revenue_e_pass') }}"></div>
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;">Other Revenue (IGR)</label><input type="number" name="local_revenue_other" class="ni fin-local-rev" min="0" step="any" placeholder="0" value="{{ old('local_revenue_other') }}"></div>
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;color:var(--nis-700);">Total Local Revenue (Auto-Calculated)</label><input type="number" id="fin-local-revenue-total" class="ni" readonly value="0" style="background:#f0fdf4;font-weight:700;color:var(--nis-800);" data-calc-sum=".fin-local-rev"></div>
    </div>
</div>

<div class="redas-card" style="margin-bottom:14px;">
    <div class="card-head">
        <div class="card-head-title">
            <div class="card-head-icon" style="background:#eff6ff;color:#1d4ed8;">
                <i class="fas fa-list-check"></i>
            </div>
            Expenditure and Budget
        </div>
    </div>
    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;">Foreign Revenue Total</label><input type="number" name="foreign_revenue_total" class="ni fin-foreign-rev" min="0" step="any" placeholder="0" value="{{ old('foreign_revenue_total') }}"></div>
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;color:var(--nis-700);">Total Combined Revenue (Auto-Calculated)</label><input type="number" id="fin-combined-revenue-total" class="ni" readonly value="0" style="background:#f0fdf4;font-weight:700;color:var(--nis-800);" data-calc-sum=".fin-local-rev, .fin-foreign-rev"></div>
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;">Expenditure Profile Total</label><input type="number" name="expenditure_profile_total" class="ni" min="0" step="any" placeholder="0" value="{{ old('expenditure_profile_total') }}"></div>
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;">Amount Budgeted</label><input type="number" name="budget_amount" class="ni fin-budget-amt" min="0" step="any" placeholder="0" value="{{ old('budget_amount') }}"></div>
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;">Amount Released</label><input type="number" name="release_amount" class="ni fin-release-amt" min="0" step="any" placeholder="0" value="{{ old('release_amount') }}"></div>
        <div><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;color:var(--nis-700);">Budget Balance / Unreleased (Auto-Calculated)</label><input type="number" id="fin-budget-balance" class="ni" readonly value="0" style="background:#f0fdf4;font-weight:700;color:var(--nis-800);" data-calc-diff=".fin-budget-amt|.fin-release-amt"></div>
        <div style="grid-column:1 / -1;"><label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:6px;">Challenges</label><textarea name="finance_challenges" class="ni" rows="3" placeholder="Provide details...">{{ old('finance_challenges') }}</textarea></div>
    </div>
</div>
