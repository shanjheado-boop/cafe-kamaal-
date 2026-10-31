<?php
// includes/footer.php
$all_hostellers_for_modal = get_all_hostellers($pdo);
?>
</main>

<!-- Footer -->
<footer class="bg-white border-t border-slate-200 mt-auto py-6 text-sm text-slate-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center space-x-2">
            <span class="w-6 h-6 rounded-md bg-brand-600 flex items-center justify-center text-white text-xs font-bold">
                <i class="fa-solid fa-utensils"></i>
            </span>
            <span class="font-bold text-slate-700">Cafe Kamaal Hotel Mess System</span>
            <span class="text-xs bg-slate-100 px-2 py-0.5 rounded border text-slate-600">v2.0 PHP & MySQL</span>
        </div>
        <div class="text-xs text-slate-500 text-center sm:text-right">
            <span>Automated Hotel Meal Split & Single Alphabet Code Calculation</span> &bull; 
            <span>Logged in as: <strong><?= clean($current_user['full_name'] ?? 'Guest') ?></strong> <?= (!empty($current_user['code'])) ? '(Code ' . clean($current_user['code']) . ')' : '' ?></span>
        </div>
    </div>
</footer>

<!-- Meal Add/Edit Modal (Centralized Live Calculator) -->
<div id="mealModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 backdrop-blur-sm transition-opacity flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full border border-slate-200 overflow-hidden transform transition-all scale-95 duration-200" id="mealModalContent">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-navy-900 to-slate-800 text-white px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
                <span class="w-9 h-9 rounded-xl bg-brand-600 flex items-center justify-center text-white text-base shadow">
                    <i class="fa-solid fa-calculator" id="modalIcon"></i>
                </span>
                <div>
                    <h3 class="text-lg font-bold" id="mealModalTitle">Add Hotel Meal Entry</h3>
                    <p class="text-xs text-slate-300">Assign cost by hosteller single-letter codes</p>
                </div>
            </div>
            <button type="button" onclick="closeMealModal()" class="text-slate-400 hover:text-white transition p-1 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Meal Form -->
        <form action="meal_actions.php" method="POST" id="mealForm" class="p-6 space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="meal_id" id="formMealId" value="">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="month_id" value="<?= $current_month['id'] ?? 1 ?>">

            <!-- Notice on editing -->
            <div id="editNoticeBox" class="hidden bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800 flex items-center gap-2">
                <i class="fa-solid fa-circle-info text-amber-500"></i>
                <span>Editing this meal will recalculate all divider shares and record your details in the audit trail.</span>
            </div>

            <!-- Meal Title -->
            <div>
                <label for="meal_title" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Meal Description / Item Name <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="title" id="meal_title" required placeholder="e.g. Chicken Handi + Rotis + Salad"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm font-medium">
            </div>

            <!-- Date, Time, and Type -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label for="meal_date" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Date</label>
                    <input type="date" name="meal_date" id="meal_date" required value="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs">
                </div>
                <div>
                    <label for="meal_time" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Time</label>
                    <input type="time" name="meal_time" id="meal_time" required value="<?= date('H:i') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs">
                </div>
                <div>
                    <label for="meal_type" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Meal Type</label>
                    <select name="meal_type" id="meal_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs font-medium">
                        <option value="Breakfast">Breakfast</option>
                        <option value="Lunch" selected>Lunch</option>
                        <option value="Dinner">Dinner</option>
                        <option value="Tea & Snacks">Tea & Snacks</option>
                        <option value="Special Mess">Special Mess</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <!-- Total Price -->
            <div>
                <label for="total_price" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Total Meal Price (PKR / Rs.) <span class="text-rose-500">*</span>
                </label>
                <div class="relative rounded-lg shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 font-bold text-sm">
                        Rs.
                    </div>
                    <input type="number" step="0.01" min="1" name="total_price" id="total_price" required placeholder="1200.00"
                           oninput="updateCalculationPreview()"
                           class="w-full pl-12 pr-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-base font-bold text-slate-800">
                </div>
            </div>

            <!-- Divider Selection (Codes of Hostellers who ate this meal) -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Divider Codes (Who Ate This Meal?) <span class="text-rose-500">*</span>
                    </label>
                    <div class="text-[11px] space-x-2">
                        <button type="button" onclick="selectAllCodes(true)" class="text-brand-600 hover:text-brand-800 font-semibold underline">Select All</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" onclick="selectAllCodes(false)" class="text-slate-500 hover:text-slate-700 underline">Clear</button>
                    </div>
                </div>

                <!-- Hosteller Badges Multi-Select Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 bg-slate-50 border border-slate-200 rounded-xl max-h-48 overflow-y-auto">
                    <?php foreach ($all_hostellers_for_modal as $hosteller): ?>
                        <label class="flex items-center p-2 rounded-lg border border-slate-200 bg-white hover:border-brand-400 cursor-pointer transition select-none group has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/60">
                            <input type="checkbox" name="eater_ids[]" value="<?= $hosteller['id'] ?>"
                                   data-code="<?= clean($hosteller['code']) ?>"
                                   data-name="<?= clean($hosteller['full_name']) ?>"
                                   onchange="updateCalculationPreview()"
                                   class="hosteller-check w-4 h-4 text-brand-600 border-slate-300 rounded focus:ring-brand-500 mr-2">
                            <span class="w-6 h-6 rounded-md bg-slate-800 group-has-[:checked]:bg-brand-600 text-white font-mono font-bold text-xs flex items-center justify-center mr-1.5 transition">
                                <?= clean($hosteller['code']) ?>
                            </span>
                            <span class="text-xs font-medium text-slate-700 truncate" title="<?= clean($hosteller['full_name']) ?>">
                                <?= clean($hosteller['full_name']) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- LIVE CALCULATION PREVIEW BOX (Divider Formula Result) -->
            <div class="bg-gradient-to-br from-brand-50 to-amber-50/80 border-2 border-brand-200 rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between text-xs text-brand-900 font-semibold mb-1">
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-calculator text-brand-600"></i>
                        Live Calculation Breakdown:
                    </span>
                    <span id="previewDividerFormula" class="font-mono text-slate-600">Rs. 0 ÷ 0 eaters</span>
                </div>
                <div class="flex items-baseline justify-between mt-2 pt-2 border-t border-brand-200/60">
                    <div>
                        <span class="text-xs text-slate-600">Each Hosteller Pays:</span>
                        <div class="text-xl font-extrabold text-brand-700" id="previewPerPerson">Rs. 0.00</div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-600">Selected Dividers:</span>
                        <div class="text-xs font-mono font-bold text-slate-800" id="previewSelectedCodes">None</div>
                    </div>
                </div>
            </div>

            <!-- Optional Change Reason (when editing) -->
            <div id="changeReasonField" class="hidden">
                <label for="edit_summary" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Reason for Edit / What was changed?
                </label>
                <input type="text" name="edit_summary" id="edit_summary" placeholder="e.g. Corrected price from 1000 to 1200 and added Code C"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs">
            </div>

            <!-- Actions -->
            <div class="pt-2 flex items-center justify-end space-x-3 border-t border-slate-200">
                <button type="button" onclick="closeMealModal()" class="px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                    Cancel
                </button>
                <button type="submit" id="submitMealBtn" class="px-5 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold shadow-md hover:shadow transition flex items-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    <span id="submitBtnText">Save Meal Entry</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
