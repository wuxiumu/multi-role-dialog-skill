import { request } from './client.js'

export async function fetchLlmProviders() {
  try {
    const data = await request('/llm-providers.php')
    return {
      llm_enabled: !!data?.llm_enabled,
      default: data?.default || 'none',
      providers: Array.isArray(data?.providers) ? data.providers : [],
    }
  } catch {
    return {
      llm_enabled: false,
      default: 'none',
      providers: [{ id: 'none', name: '不使用模型', ready: true, desc: '仅离线匹配' }],
    }
  }
}

/**
 * 探测大模型 Key 是否可用。
 * @param {{ mode: 'system'|'manual', provider?, base_url?, api_key?, model?, prompt?, token }} opts
 */
export async function testLlmKey({
  mode = 'system',
  provider,
  base_url,
  api_key,
  model,
  prompt,
  token,
}) {
  const body =
    mode === 'manual'
      ? { mode: 'manual', base_url, api_key, model, prompt }
      : { mode: 'system', provider, prompt }

  return request('/llm-key-test.php', {
    method: 'POST',
    headers: token ? { Authorization: `Bearer ${token}` } : {},
    body: JSON.stringify(body),
  })
}

/** 手动填写时的常用预设（仅填地址/模型名，不含密钥） */
export const LLM_MANUAL_PRESETS = [
  {
    id: 'deepseek',
    name: 'DeepSeek',
    base_url: 'https://api.deepseek.com',
    model: 'deepseek-chat',
  },
  {
    id: 'qwen-dashscope',
    name: '通义千问（DashScope 兼容）',
    base_url: 'https://dashscope.aliyuncs.com/compatible-mode/v1',
    model: 'qwen-plus',
  },
  {
    id: 'openai',
    name: 'OpenAI',
    base_url: 'https://api.openai.com/v1',
    model: 'gpt-4o-mini',
  },
]
