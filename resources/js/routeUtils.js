import { intlLocale, t } from './i18n';
export function distanceLabel(meters) {
  return t('{value} km', {
    value: new Intl.NumberFormat(intlLocale(), {
      maximumFractionDigits: 1,
    }).format(meters / 1000),
  });
}
export function durationLabel(seconds) {
  const minutes = Math.ceil(seconds / 60);
  return minutes >= 60
    ? t('{hours} h {minutes} min', {
        hours: Math.floor(minutes / 60),
        minutes: minutes % 60,
      })
    : t('{minutes} min', { minutes });
}
export function directionInstruction(step) {
  let key = 'Continue';
  if (step.type === 'depart') key = 'Start driving';
  else if (step.type === 'arrive') key = 'Arrive at destination';
  else if (step.type === 'merge') key = 'Merge';
  else if (step.exit)
    return (
      t('Take exit {exit}', { exit: step.exit }) +
      (step.name ? ` · ${step.name}` : '')
    );
  else if (step.modifier)
    key =
      {
        left: 'Turn left',
        right: 'Turn right',
        'slight left': 'Keep left',
        'slight right': 'Keep right',
        'sharp left': 'Turn sharply left',
        'sharp right': 'Turn sharply right',
        uturn: 'Make a U-turn',
        straight: 'Continue straight',
      }[step.modifier] || 'Continue';
  return t(key) + (step.name ? ` · ${step.name}` : '');
}
