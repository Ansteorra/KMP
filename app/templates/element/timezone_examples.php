<?php

/**
 * Example Template: Timezone Usage Demonstration
 * 
 * This template demonstrates various timezone handling patterns in KMP.
 * Copy these examples into your own templates as needed.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering Example gathering entity
 * @var \App\Model\Entity\Member $currentUser Current authenticated user
 */
?>

<!-- ===== EXAMPLE 1: Basic DateTime Display ===== -->
<div class="card mb-3">
    <div class="card-header">
        <h5>Example 1: Basic DateTime Display</h5>
    </div>
    <div class="card-body">
        <p><strong>Gathering Start:</strong>
            <?= $this->Timezone->format($gathering->start_date) ?>
        </p>

        <p><strong>With Timezone:</strong>
            <?= $this->Timezone->format($gathering->start_date, null, true) ?>
        </p>

        <p><strong>Custom Format:</strong>
            <?= $this->Timezone->format($gathering->start_date, 'l, F j, Y \a\t g:i A') ?>
        </p>

        <?= $this->Timezone->notice('text-muted small') ?>
    </div>
</div>

<!-- ===== EXAMPLE 2: Date Ranges ===== -->
<div class="card mb-3">
    <div class="card-header">
        <h5>Example 2: Date Ranges</h5>
    </div>
    <div class="card-body">
        <p><strong>Basic Range:</strong>
            <?= $this->Timezone->range($gathering->start_date, $gathering->end_date) ?>
        </p>

        <p><strong>Smart Range (same day shows time only):</strong>
            <?= $this->Timezone->smartRange($gathering->start_date, $gathering->end_date) ?>
        </p>

        <p><strong>Date Only:</strong>
            <?= $this->Timezone->date($gathering->start_date) ?> -
            <?= $this->Timezone->date($gathering->end_date) ?>
        </p>
    </div>
</div>

<!-- ===== EXAMPLE 3: Form with Timezone Conversion ===== -->
<div class="card mb-3">
    <div class="card-header">
        <h5>Example 3: Form with Timezone Conversion (Stimulus Controller)</h5>
    </div>
    <div class="card-body">
        <?= $this->Form->create($gathering, [
            'data-controller' => 'timezone-input'
        ]) ?>

        <div class="row">
            <div class="col-md-6">
                <?= $this->Form->control('start_date', [
                    'type' => 'datetime-local',
                    'label' => 'Start Date/Time',
                    'data-timezone-input-target' => 'datetimeInput',
                    'data-utc-value' => $gathering->start_date ? $gathering->start_date->toIso8601String() : '',
                ]) ?>
            </div>

            <div class="col-md-6">
                <?= $this->Form->control('end_date', [
                    'type' => 'datetime-local',
                    'label' => 'End Date/Time',
                    'data-timezone-input-target' => 'datetimeInput',
                    'data-utc-value' => $gathering->end_date ? $gathering->end_date->toIso8601String() : '',
                ]) ?>
            </div>
        </div>

        <div class="mb-3">
            <small data-timezone-input-target="notice" class="text-muted"></small>
        </div>

        <?= $this->Form->button('Save', ['class' => 'btn btn-primary']) ?>
        <?= $this->Form->end() ?>
    </div>
</div>

<!-- ===== EXAMPLE 4: Manual Form with Helper ===== -->
<div class="card mb-3">
    <div class="card-header">
        <h5>Example 4: Manual Form (Helper-based Conversion)</h5>
    </div>
    <div class="card-body">
        <?= $this->Form->create($gathering) ?>

        <?= $this->Form->control('start_date', [
            'type' => 'datetime-local',
            'label' => 'Start Date/Time',
            'value' => $this->Timezone->forInput($gathering->start_date),
            'help' => 'Times are in your timezone: ' . $this->Timezone->getUserTimezone()
        ]) ?>

        <?= $this->Form->control('end_date', [
            'type' => 'datetime-local',
            'label' => 'End Date/Time',
            'value' => $this->Timezone->forInput($gathering->end_date),
        ]) ?>

        <?= $this->Timezone->notice('alert alert-info') ?>

        <?= $this->Form->button('Save (Manual Conversion)', ['class' => 'btn btn-secondary']) ?>
        <?= $this->Form->end() ?>

        <div class="alert alert-warning mt-3">
            <strong>Note:</strong> With manual conversion, you must convert input back to UTC
            in the controller using <code>TimezoneHelper::toUtc()</code>
        </div>
    </div>
</div>

<!-- ===== EXAMPLE 5: Timezone Selector ===== -->
<div class="card mb-3">
    <div class="card-header">
        <h5>Example 5: Timezone Selector (Member Profile)</h5>
    </div>
    <div class="card-body">
        <?= $this->Form->create($currentUser) ?>

        <?= $this->Form->control('timezone', [
            'type' => 'select',
            'options' => $this->Timezone->getTimezoneOptions(true), // common timezones only
            'empty' => sprintf(
                '(Use Application Default: %s)',
                \App\KMP\TimezoneHelper::getAppTimezone() ?? 'UTC'
            ),
            'label' => 'Your Timezone',
            'help' => 'Select your timezone to see dates and times in your local time'
        ]) ?>

        <div class="alert alert-info">
            <strong>Current Timezone:</strong> <?= $this->Timezone->getUserTimezone() ?>
            (<?= $this->Timezone->getAbbreviation() ?>)
        </div>

        <?= $this->Form->button('Update Timezone', ['class' => 'btn btn-primary']) ?>
        <?= $this->Form->end() ?>
    </div>
</div>

<!-- ===== EXAMPLE 6: JavaScript Integration ===== -->
<div class="card mb-3">
    <div class="card-header">
        <h5>Example 6: JavaScript Integration</h5>
    </div>
    <div class="card-body">
        <div id="js-timezone-demo">
            <p>Loading...</p>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const demo = document.getElementById('js-timezone-demo');

                // Detect timezone
                const timezone = KMP_Timezone.detectTimezone();

                // Format a UTC datetime
                const utcTime = "<?= $gathering->start_date->toIso8601String() ?>";
                const formatted = KMP_Timezone.formatDateTime(utcTime, timezone);

                // Display results
                demo.innerHTML = `
                <p><strong>Browser Timezone:</strong> ${timezone}</p>
                <p><strong>UTC Time:</strong> ${utcTime}</p>
                <p><strong>Local Time:</strong> ${formatted}</p>
                <p><strong>Timezone Abbreviation:</strong> ${KMP_Timezone.getAbbreviation(timezone)}</p>
            `;
            });
        </script>
    </div>
</div>

<!-- ===== EXAMPLE 7: Calendar/Schedule Display ===== -->
<div class="card mb-3">
    <div class="card-header">
        <h5>Example 7: Calendar/Schedule Display</h5>
    </div>
    <div class="card-body">
        <div class="gathering-card">
            <h4><?= h($gathering->name) ?></h4>

            <div class="gathering-details">
                <div class="detail-row">
                    <i class="bi bi-calendar-event"></i>
                    <strong>When:</strong>
                    <?php if ($gathering->is_multi_day): ?>
                        <?= $this->Timezone->date($gathering->start_date) ?> -
                        <?= $this->Timezone->date($gathering->end_date) ?>
                    <?php else: ?>
                        <?= $this->Timezone->format($gathering->start_date, 'l, F j, Y') ?>
                    <?php endif; ?>
                </div>

                <div class="detail-row">
                    <i class="bi bi-clock"></i>
                    <strong>Time:</strong>
                    <?= $this->Timezone->time($gathering->start_date) ?>
                    <?php if ($gathering->end_date): ?>
                        - <?= $this->Timezone->time($gathering->end_date) ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-2">
                <?= $this->Timezone->notice('text-muted small') ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== Controller Code Example ===== -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5>Controller Code Example</h5>
    </div>
    <div class="card-body">
        <p>In your controller, convert user input to UTC before saving:</p>

        <pre><code class="language-php">use App\KMP\TimezoneHelper;

public function edit($id = null)
{
    $gathering = $this->Gatherings->get($id);
    
    if ($this->request->is(['post', 'put'])) {
        $data = $this->request->getData();
        
        // Convert user's local time to UTC for storage
        $data['start_date'] = TimezoneHelper::toUtc(
            $data['start_date'],
            TimezoneHelper::getUserTimezone($this->Authentication->getIdentity())
        );
        
        $data['end_date'] = TimezoneHelper::toUtc(
            $data['end_date'],
            TimezoneHelper::getUserTimezone($this->Authentication->getIdentity())
        );
        
        $gathering = $this->Gatherings->patchEntity($gathering, $data);
        
        if ($this->Gatherings->save($gathering)) {
            $this->Flash->success(__('The gathering has been saved.'));
            return $this->redirect(['action' => 'view', $gathering->id]);
        }
        $this->Flash->error(__('The gathering could not be saved.'));
    }
    
    $this->set(compact('gathering'));
}</code></pre>
    </div>
</div>

<style>
    .gathering-card {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        padding: 1rem;
    }

    .detail-row {
        margin-bottom: 0.5rem;
    }

    .detail-row i {
        margin-right: 0.5rem;
        color: #6c757d;
    }

    pre {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 0.25rem;
        overflow-x: auto;
    }

    code {
        color: #d63384;
    }
</style>