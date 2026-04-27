(function (Drupal, once) {
  'use strict';

  function toNumber(value) {
    var number = parseFloat(value);
    return Number.isNaN(number) ? 0 : number;
  }

  function calculateVatAmount(rateInput, vatPercentInput, vatAmountInput) {
    if (!rateInput || !vatPercentInput || !vatAmountInput) {
      return;
    }
    var rate = toNumber(rateInput.value);
    var vatPercent = toNumber(vatPercentInput.value);
    var vatAmount = (rate * vatPercent) / 100;
    vatAmountInput.value = vatAmount.toFixed(2);
  }

  Drupal.behaviors.roomPriceVatCalculation = {
    attach: function attach(context) {
      once('room-price-vat-calculation', '.room-form-wrapper', context).forEach(function (wrapperRoot) {
        var singleNightlyRate = wrapperRoot.querySelector('[name="single_nightly_rate"]');
        var singleMonthlyRate = wrapperRoot.querySelector('[name="single_monthly_rate"]');
        var nightlyVatPercent = wrapperRoot.querySelector('[name="nightly_vat_percent"]');
        var monthlyVatPercent = wrapperRoot.querySelector('[name="monthly_vat_percent"]');
        var nightlyVatAmount = wrapperRoot.querySelector('[name="nightly_vat_amount"]');
        var monthlyVatAmount = wrapperRoot.querySelector('[name="monthly_vat_amount"]');
        var doubleNightlyRate = wrapperRoot.querySelector('[name="double_nightly_rate"]');
        var doubleMonthlyRate = wrapperRoot.querySelector('[name="double_monthly_rate"]');
        var doubleNightlyVatPt = wrapperRoot.querySelector('[name="double_nightly_rate_vat_pt"]');
        var doubleMonthlyVatPt = wrapperRoot.querySelector('[name="double_monthly_rate_vat_pt"]');
        var doubleNightlyVatAm = wrapperRoot.querySelector('[name="double_nightly_rate_vat_am"]');
        var doubleMonthlyVatAm = wrapperRoot.querySelector('[name="double_monthly_rate_vat_am"]');

        var recalculateNightly = function recalculateNightly() {
          calculateVatAmount(singleNightlyRate, nightlyVatPercent, nightlyVatAmount);
        };
        var recalculateMonthly = function recalculateMonthly() {
          calculateVatAmount(singleMonthlyRate, monthlyVatPercent, monthlyVatAmount);
        };
        var recalculateDoubleNightly = function recalculateDoubleNightly() {
          calculateVatAmount(doubleNightlyRate, doubleNightlyVatPt, doubleNightlyVatAm);
        };
        var recalculateDoubleMonthly = function recalculateDoubleMonthly() {
          calculateVatAmount(doubleMonthlyRate, doubleMonthlyVatPt, doubleMonthlyVatAm);
        };

        if (singleNightlyRate && nightlyVatPercent) {
          singleNightlyRate.addEventListener('input', recalculateNightly);
          nightlyVatPercent.addEventListener('input', recalculateNightly);
        }
        if (singleMonthlyRate && monthlyVatPercent) {
          singleMonthlyRate.addEventListener('input', recalculateMonthly);
          monthlyVatPercent.addEventListener('input', recalculateMonthly);
        }
        if (doubleNightlyRate && doubleNightlyVatPt) {
          doubleNightlyRate.addEventListener('input', recalculateDoubleNightly);
          doubleNightlyVatPt.addEventListener('input', recalculateDoubleNightly);
        }
        if (doubleMonthlyRate && doubleMonthlyVatPt) {
          doubleMonthlyRate.addEventListener('input', recalculateDoubleMonthly);
          doubleMonthlyVatPt.addEventListener('input', recalculateDoubleMonthly);
        }
        recalculateNightly();
        recalculateMonthly();
        recalculateDoubleNightly();
        recalculateDoubleMonthly();
      });
    }
  };
})(Drupal, once);
