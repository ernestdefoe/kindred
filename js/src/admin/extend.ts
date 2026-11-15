import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

const t = (key: string) => app.translator.trans(`ernestdefoe-kindred.admin.${key}`);

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'ernestdefoe-kindred.limit',
      type: 'number',
      label: t('limit'),
      help: t('limit_help'),
      min: 1,
      max: 20,
      placeholder: '5',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-kindred.max_age_days',
      type: 'number',
      label: t('max_age'),
      help: t('max_age_help'),
      min: 0,
      placeholder: '0',
    }))
    .setting(() => ({
      setting: 'ernestdefoe-kindred.extra_stopwords',
      type: 'textarea',
      label: t('extra_stopwords'),
      help: t('extra_stopwords_help'),
      rows: 3,
      placeholder: t('extra_stopwords_placeholder'),
    }))
    .setting(() => ({
      setting: 'ernestdefoe-kindred.sidebar',
      type: 'boolean',
      label: t('sidebar'),
      help: t('sidebar_help'),
    })),
];
